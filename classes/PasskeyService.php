<?php

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

use ParagonIE\ConstantTime\Base64UrlSafe;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialLoader;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialSource;
use Webauthn\PublicKeyCredentialUserEntity;

class PasskeyService
{
    private const SESSION_CREATE = '_mfaadmin_passkey_create_options';
    private const SESSION_AUTH   = '_mfaadmin_passkey_auth_options';

    private string $rpName;
    private string $rpId;
    private string $origin;

    public function __construct()
    {
        $this->rpName = (string) Configuration::get('PS_SHOP_NAME');

        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $rpIdFromHost = explode(':', $host)[0];

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

        $this->rpId   = $rpIdFromHost;
        $this->origin = $scheme . '://' . $host;
    }

    // -------------------------------------------------------------------------
    // Registrazione
    // -------------------------------------------------------------------------

    public function getRegistrationOptions(int $employeeId, string $email, string $name): array
    {
        $challenge = random_bytes(32);

        $rp   = PublicKeyCredentialRpEntity::create($this->rpName, $this->rpId);
        $user = PublicKeyCredentialUserEntity::create($email, (string) $employeeId, $name);

        $excludeCredentials = $this->buildDescriptors($employeeId);

        $options = PublicKeyCredentialCreationOptions::create(
            rp: $rp,
            user: $user,
            challenge: $challenge,
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(-7),   // ES256
                PublicKeyCredentialParameters::createPk(-257), // RS256
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                authenticatorAttachment: null,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            attestation: 'none',
            excludeCredentials: $excludeCredentials,
        );

        $optionsArray = @$options->jsonSerialize();

        // Salva in sessione con scadenza di 10 minuti
        $_SESSION[self::SESSION_CREATE] = json_encode([
            'options' => $optionsArray,
            'exp'     => time() + 600,
        ], JSON_THROW_ON_ERROR);

        return $optionsArray;
    }

    public function verifyRegistration(int $employeeId, string $label, string $credentialJson): EmployeePasskey
    {
        $wrapperJson = $_SESSION[self::SESSION_CREATE] ?? null;

        if (!$wrapperJson) {
            throw new RuntimeException('Sessione di registrazione scaduta. Riprova.');
        }

        $wrapper = json_decode($wrapperJson, true, 512, JSON_THROW_ON_ERROR);

        // Controlla scadenza challenge
        if (!isset($wrapper['exp']) || time() > (int) $wrapper['exp']) {
            unset($_SESSION[self::SESSION_CREATE]);
            throw new RuntimeException('Challenge scaduta. Riprova.');
        }

        $optionsArray = $wrapper['options'];

        /** @var PublicKeyCredentialCreationOptions $options */
        $options    = @PublicKeyCredentialCreationOptions::createFromArray($optionsArray);
        $credential = $this->loadCredential($credentialJson);

        $factory = new CeremonyStepManagerFactory();

        $validator = AuthenticatorAttestationResponseValidator::create(
            null, null, null, null, null, $factory->creationCeremony($this->securedRpIds())
        );

        $source = $validator->check($credential->response, $options, $this->rpId);

        unset($_SESSION[self::SESSION_CREATE]);

        return $this->savePasskey($employeeId, $label, $source);
    }

    // -------------------------------------------------------------------------
    // Autenticazione
    // -------------------------------------------------------------------------

    public function getAuthenticationOptions(int $employeeId): array
    {
        $descriptors = $this->buildDescriptors($employeeId);

        if (empty($descriptors)) {
            throw new RuntimeException('Nessuna passkey registrata.');
        }

        $challenge = random_bytes(32);

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId,
            allowCredentials: $descriptors,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_PREFERRED,
        );

        $optionsArray = @$options->jsonSerialize();

        // Salva in sessione con scadenza di 10 minuti
        $_SESSION[self::SESSION_AUTH] = json_encode([
            'options' => $optionsArray,
            'exp'     => time() + 600,
        ], JSON_THROW_ON_ERROR);

        return $optionsArray;
    }

    public function verifyAuthentication(int $employeeId, string $credentialJson): bool
    {
        $wrapperJson = $_SESSION[self::SESSION_AUTH] ?? null;

        if (!$wrapperJson) {
            return false;
        }

        try {
            $wrapper = json_decode($wrapperJson, true, 512, JSON_THROW_ON_ERROR);

            // Controlla scadenza challenge
            if (!isset($wrapper['exp']) || time() > (int) $wrapper['exp']) {
                unset($_SESSION[self::SESSION_AUTH]);
                return false;
            }

            $optionsArray = $wrapper['options'];

            /** @var PublicKeyCredentialRequestOptions $options */
            $options    = @PublicKeyCredentialRequestOptions::createFromArray($optionsArray);
            $credential = $this->loadCredential($credentialJson);

            $credentialId = Base64UrlSafe::encodeUnpadded($credential->rawId);
            $passkey      = EmployeePasskey::getByCredentialId($credentialId, $employeeId);

            if (!$passkey) {
                return false;
            }

            $sourceArray = json_decode($passkey->credential_source, true, 512, JSON_THROW_ON_ERROR);

            /** @var PublicKeyCredentialSource $source */
            $source = @PublicKeyCredentialSource::createFromArray($sourceArray);

            $factory = new CeremonyStepManagerFactory();

            $validator = AuthenticatorAssertionResponseValidator::create(
                null, null, null, null, null, $factory->requestCeremony($this->securedRpIds())
            );

            $updatedSource = $validator->check(
                $source,
                $credential->response,
                $options,
                $this->rpId,
                (string) $employeeId
            );

            $updatedArray = @$updatedSource->jsonSerialize();

            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'employee_passkey`
                 SET `credential_source` = \'' . pSQL(json_encode($updatedArray, JSON_THROW_ON_ERROR)) . '\'
                 WHERE `id_passkey` = ' . (int) $passkey->id
            );

            unset($_SESSION[self::SESSION_AUTH]);

            return true;
        } catch (Throwable $e) {
            PrestaShopLogger::addLog('[mfaadmin] PasskeyService::verifyAuthentication: ' . $e->getMessage(), 3);

            return false;
        }
    }

    // -------------------------------------------------------------------------
    // Gestione passkey
    // -------------------------------------------------------------------------

    /**
     * @return EmployeePasskey[]
     */
    public function getPasskeysForEmployee(int $employeeId): array
    {
        return EmployeePasskey::getByEmployeeId($employeeId);
    }

    public function deletePasskey(int $passkeyId, int $employeeId): bool
    {
        $passkey = EmployeePasskey::getByCredentialId(
            Db::getInstance()->getValue(
                'SELECT `credential_id` FROM `' . _DB_PREFIX_ . 'employee_passkey`
                 WHERE `id_passkey` = ' . $passkeyId . ' AND `id_employee` = ' . $employeeId
            ) ?: '',
            $employeeId
        );

        if (!$passkey) {
            return false;
        }

        return (bool) $passkey->delete();
    }

    // -------------------------------------------------------------------------
    // Helpers privati
    // -------------------------------------------------------------------------

    /** @return string[] rpId esclusi dal controllo HTTPS (non vuoto solo su origini HTTP, es. sviluppo locale) */
    private function securedRpIds(): array
    {
        return str_starts_with($this->origin, 'http://') ? [$this->rpId] : [];
    }

    private function loadCredential(string $credentialJson): PublicKeyCredential
    {
        $manager = new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ]);

        $loader = PublicKeyCredentialLoader::create(
            AttestationObjectLoader::create($manager)
        );

        return $loader->load($credentialJson);
    }

    /** @return PublicKeyCredentialDescriptor[] Descrittori delle passkey esistenti dell'employee */
    private function buildDescriptors(int $employeeId): array
    {
        return array_map(
            static fn(EmployeePasskey $pk) => PublicKeyCredentialDescriptor::create(
                type: PublicKeyCredentialDescriptor::CREDENTIAL_TYPE_PUBLIC_KEY,
                id:   Base64UrlSafe::decodeNoPadding($pk->credential_id),
            ),
            EmployeePasskey::getByEmployeeId($employeeId)
        );
    }

    private function savePasskey(
        int $employeeId,
        string $label,
        PublicKeyCredentialSource $source
    ): EmployeePasskey {
        $sourceArray = @$source->jsonSerialize();

        $passkey = new EmployeePasskey();
        $passkey->id_employee       = $employeeId;
        $passkey->credential_id     = Base64UrlSafe::encodeUnpadded($source->publicKeyCredentialId);
        $passkey->credential_source = json_encode($sourceArray, JSON_THROW_ON_ERROR);
        $passkey->device_label      = $label ?: 'Passkey';
        $passkey->date_add          = date('Y-m-d H:i:s');

        if (!$passkey->add()) {
            throw new RuntimeException('Errore nel salvataggio della passkey.');
        }

        return $passkey;
    }
}