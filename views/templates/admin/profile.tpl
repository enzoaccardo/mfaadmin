<div class="container-fluid">
    <div class="row justify-content-center">
        <div class="col-xl-8">
            <h1 class="h2 mb-4">🔐 Gestione MFA &amp; Passkey</h1>

            {if $mfa_success}
                <div class="alert alert-success">{$mfa_success|escape:'html'}</div>
            {/if}
            {if $mfa_error}
                <div class="alert alert-danger">{$mfa_error|escape:'html'}</div>
            {/if}

            {* ---- TOTP ---- *}
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">Autenticazione TOTP (Google Authenticator)</h5>
                </div>
                <div class="card-body">
                    {if $mfa_enabled}
                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                            <div>
                                <span class="badge badge-success mr-2">Attivo</span>
                                Codici di recupero disponibili: <strong>{$recovery_codes_count}</strong>
                            </div>
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-secondary" data-toggle="modal" data-target="#modalRegen">
                                    Rigenera codici
                                </button>
                                <button class="btn btn-sm btn-outline-danger" data-toggle="modal" data-target="#modalDisable">
                                    Disabilita
                                </button>
                            </div>
                        </div>
                    {else}
                        <p class="text-muted mb-3">MFA non attivato. Abilita l'autenticazione a due fattori per maggiore sicurezza.</p>
                        <form method="post" action="{$form_action|escape:'html'}">
                            <input type="hidden" name="token" value="{$token}">
                            <input type="hidden" name="submitEnableMfa" value="1">
                            <button type="submit" class="btn btn-primary">Abilita MFA</button>
                        </form>
                    {/if}
                </div>
            </div>

            {* ---- Passkey ---- *}
            <div class="card mb-4">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5 class="mb-0">Passkey (WebAuthn / FIDO2)</h5>
                    <button id="passkey-register-btn" class="btn btn-sm btn-primary"
                        {if !$mfa_enabled}disabled title="Abilita prima MFA TOTP"{/if}>
                        + Aggiungi passkey
                    </button>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <input type="text" id="passkey-label" class="form-control"
                               placeholder="Nome dispositivo (es. MacBook Pro)">
                        <div id="passkey-register-status" class="small mt-1"></div>
                    </div>

                    {if $passkeys}
                        <ul class="list-group" id="passkey-list">
                            {foreach $passkeys as $pk}
                                <li class="list-group-item d-flex align-items-center justify-content-between"
                                    id="passkey-row-{$pk->id|intval}">
                                    <span>
                                        <strong>{$pk->device_label|escape:'html'}</strong>
                                        <small class="text-muted ml-2">{$pk->date_add|escape:'html'}</small>
                                    </span>
                                    <button class="btn btn-sm btn-outline-danger"
                                            data-passkey-delete="{$pk->id|intval}">Rimuovi</button>
                                </li>
                            {/foreach}
                        </ul>
                    {else}
                        <p class="text-muted small mb-0">Nessuna passkey registrata.</p>
                    {/if}
                </div>
            </div>

        </div>
    </div>
</div>

{* Modal disabilita MFA *}
<div class="modal fade" id="modalDisable" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <form method="post" action="{$form_action|escape:'html'}">
                <input type="hidden" name="token" value="{$token}">
                <input type="hidden" name="submitDisableMfa" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Disabilita MFA</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Inserisci il codice TOTP corrente per confermare.</p>
                    <input type="text" name="confirm_code" class="form-control text-center"
                           placeholder="000000" maxlength="6" inputmode="numeric" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-danger">Disabilita</button>
                </div>
            </form>
        </div>
    </div>
</div>

{* Modal rigenera codici *}
<div class="modal fade" id="modalRegen" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-sm" role="document">
        <div class="modal-content">
            <form method="post" action="{$form_action|escape:'html'}">
                <input type="hidden" name="token" value="{$token}">
                <input type="hidden" name="submitRegenerateCodes" value="1">
                <div class="modal-header">
                    <h5 class="modal-title">Rigenera codici di recupero</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">I vecchi codici saranno invalidati. Inserisci il codice TOTP corrente.</p>
                    <input type="text" name="confirm_code_regen" class="form-control text-center"
                           placeholder="000000" maxlength="6" inputmode="numeric" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Annulla</button>
                    <button type="submit" class="btn btn-warning">Rigenera</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.__PASSKEY_OPTIONS_URL__        = '{$passkey_ajax_url|escape:'javascript'}&action=registerOptions';
    window.__PASSKEY_REGISTER_URL__       = '{$passkey_ajax_url|escape:'javascript'}&action=register';
    window.__PASSKEY_DELETE_BASE_URL__    = '{$passkey_ajax_url|escape:'javascript'}&action=delete&passkeyId=';
</script>