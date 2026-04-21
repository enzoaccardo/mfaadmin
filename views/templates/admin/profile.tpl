<div class="container-fluid">
    <div class="row">
        <div class="col-xl-7 col-lg-9">

            {if $mfa_success}
                <div class="alert alert-success alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    <i class="material-icons" style="font-size:1rem;vertical-align:middle;margin-right:.25rem">check_circle</i>
                    {$mfa_success|escape:'html'}
                </div>
            {/if}
            {if $mfa_error}
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
                    <i class="material-icons" style="font-size:1rem;vertical-align:middle;margin-right:.25rem">error_outline</i>
                    {$mfa_error|escape:'html'}
                </div>
            {/if}

            {* ========== TOTP ========== *}
            <div class="card mfa-profile-card mb-4">
                <div class="card-header">
                    <i class="material-icons">smartphone</i>
                    Autenticazione TOTP (Google Authenticator)
                </div>
                <div class="card-body">
                    {if $mfa_enabled}
                        <span class="status-badge on">
                            <i class="material-icons">check_circle</i>
                            Attivo
                        </span>
                        <div class="rc-info">Codici di recupero disponibili: <strong>{$recovery_codes_count}</strong></div>

                        <div class="action-row">
                            <button type="button" class="btn-mfa-act" id="btn-show-regen">
                                <i class="material-icons">refresh</i>
                                Rigenera codici
                            </button>
                            <button type="button" class="btn-mfa-act btn-mfa-act-danger" id="btn-show-disable">
                                <i class="material-icons">no_encryption</i>
                                Disabilita MFA
                            </button>
                        </div>

                        <div id="regen-confirm" class="inline-confirm" style="display:none">
                            <p>Inserisci il codice TOTP corrente per rigenerare i codici di recupero.</p>
                            <form method="post" action="{$form_action|escape:'html'}">
                                <input type="hidden" name="token" value="{$token}">
                                <input type="hidden" name="submitRegenerateCodes" value="1">
                                <div class="confirm-row">
                                    <input type="text" name="confirm_code_regen" class="otp-confirm"
                                           inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="off">
                                    <button type="submit" class="btn-mfa-ok">Rigenera</button>
                                    <button type="button" class="btn-mfa-cancel" id="btn-cancel-regen">Annulla</button>
                                </div>
                            </form>
                        </div>

                        <div id="disable-confirm" class="inline-confirm" style="display:none">
                            <p>Inserisci il codice TOTP corrente per disabilitare l'autenticazione a due fattori.</p>
                            <form method="post" action="{$form_action|escape:'html'}">
                                <input type="hidden" name="token" value="{$token}">
                                <input type="hidden" name="submitDisableMfa" value="1">
                                <div class="confirm-row">
                                    <input type="text" name="confirm_code" class="otp-confirm"
                                           inputmode="numeric" maxlength="6" placeholder="000000" autocomplete="off">
                                    <button type="submit" class="btn-mfa-ok btn-mfa-ok-danger">Disabilita</button>
                                    <button type="button" class="btn-mfa-cancel" id="btn-cancel-disable">Annulla</button>
                                </div>
                            </form>
                        </div>

                    {else}
                        <span class="status-badge off">
                            <i class="material-icons">lock_open</i>
                            Non attivato
                        </span>
                        <p class="text-muted mt-2 mb-3" style="font-size:.84rem">
                            Abilita l'autenticazione a due fattori per proteggere il tuo account.
                        </p>
                        <form method="post" action="{$form_action|escape:'html'}">
                            <input type="hidden" name="token" value="{$token}">
                            <input type="hidden" name="submitEnableMfa" value="1">
                            <button type="submit" class="btn-mfa-enable">
                                <i class="material-icons">security</i>
                                Abilita MFA
                            </button>
                        </form>
                    {/if}
                </div>
            </div>

            {* ========== Passkey ========== *}
            <div class="card mfa-profile-card">
                <div class="card-header">
                    <i class="material-icons">fingerprint</i>
                    Passkey (WebAuthn / FIDO2)
                </div>
                <div class="card-body">
                    <input type="text" id="passkey-label" class="pk-label-input"
                           placeholder="Nome dispositivo (es. MacBook Pro)">
                    <div id="passkey-register-status" class="small mb-2"></div>

                    <button id="passkey-register-btn" class="btn-pk-add"
                            {if !$mfa_enabled}disabled title="Abilita prima MFA TOTP"{/if}>
                        <i class="material-icons">add</i>
                        Aggiungi passkey
                    </button>

                    {if $passkeys}
                        <div id="passkey-list" style="margin-top:.9rem">
                            {foreach $passkeys as $pk}
                                <div class="pk-item" id="passkey-row-{$pk->id|intval}">
                                    <span>
                                        <span class="pk-name">{$pk->device_label|escape:'html'}</span>
                                        <span class="pk-date">{$pk->date_add|escape:'html'}</span>
                                    </span>
                                    <button class="btn-pk-del" data-passkey-delete="{$pk->id|intval}">Rimuovi</button>
                                </div>
                            {/foreach}
                        </div>
                    {else}
                        <p class="text-muted small mb-0" style="margin-top:.75rem">Nessuna passkey registrata.</p>
                    {/if}
                </div>
            </div>

        </div>
    </div>
</div>

{if $mfa_new_codes}
<div class="codes-overlay" id="codes-overlay">
    <div class="codes-dialog">
        <div class="codes-dialog-header">
            <i class="material-icons">assignment_turned_in</i>
            Nuovi codici di recupero
        </div>
        <div class="codes-dialog-body">
            <div class="codes-warning">
                <i class="material-icons">warning</i>
                <span><strong>Mostrati una sola volta.</strong> Salvali subito in un posto sicuro — servono se perdi l'accesso all'app Authenticator.</span>
            </div>
            <div class="codes-grid">
                {foreach $mfa_new_codes as $rc}
                    <div class="code-item">{$rc|escape:'html'}</div>
                {/foreach}
            </div>
            <form method="post" action="{$form_action|escape:'html'}">
                <input type="hidden" name="token" value="{$token}">
                <input type="hidden" name="submitClearNewCodes" value="1">
                <button type="submit" class="btn-mfa-ok" style="width:100%">
                    <i class="material-icons" style="font-size:1rem;vertical-align:middle;margin-right:.3rem">check</i>
                    Ho salvato i codici
                </button>
            </form>
        </div>
    </div>
</div>
{/if}

<script>
    (function () {
        function showConfirm(confirmId, btnId) {
            var panel = document.getElementById(confirmId);
            var btn   = document.getElementById(btnId);
            if (panel) panel.style.display = 'block';
            if (btn)   btn.style.display   = 'none';
            var input = panel && panel.querySelector('input[type=text]');
            if (input) { input.value = ''; input.focus(); }
        }
        function hideConfirm(confirmId, btnId) {
            var panel = document.getElementById(confirmId);
            var btn   = document.getElementById(btnId);
            if (panel) panel.style.display = 'none';
            if (btn)   btn.style.display   = '';
        }

        var btnRegen   = document.getElementById('btn-show-regen');
        var btnDisable = document.getElementById('btn-show-disable');
        var btnCancelR = document.getElementById('btn-cancel-regen');
        var btnCancelD = document.getElementById('btn-cancel-disable');

        if (btnRegen)   btnRegen.addEventListener('click',   function () { showConfirm('regen-confirm',   'btn-show-regen'); });
        if (btnDisable) btnDisable.addEventListener('click', function () { showConfirm('disable-confirm', 'btn-show-disable'); });
        if (btnCancelR) btnCancelR.addEventListener('click', function () { hideConfirm('regen-confirm',   'btn-show-regen'); });
        if (btnCancelD) btnCancelD.addEventListener('click', function () { hideConfirm('disable-confirm', 'btn-show-disable'); });

        document.querySelectorAll('.otp-confirm').forEach(function (input) {
            input.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
                if (this.value.length === 6) { this.closest('form').submit(); }
            });
        });
    })();

    window.__PASSKEY_OPTIONS_URL__     = '{$passkey_ajax_url|escape:'javascript'}&action=registerOptions';
    window.__PASSKEY_REGISTER_URL__    = '{$passkey_ajax_url|escape:'javascript'}&action=register';
    window.__PASSKEY_DELETE_BASE_URL__ = '{$passkey_ajax_url|escape:'javascript'}&action=delete&passkeyId=';
</script>
