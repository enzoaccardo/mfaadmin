{extends file='_mfa_layout.tpl'}

{block name=page_title}Verifica identità{/block}
{block name=card_icon}security{/block}
{block name=card_title}Verifica identità{/block}

{block name=content}
    {if $mfa_error}
        <div class="alert alert-danger py-2 mb-3" style="font-size:.88rem">
            <i class="material-icons" style="font-size:1rem;vertical-align:middle;margin-right:.25rem">error_outline</i>
            {$mfa_error|escape:'html'}
        </div>
    {/if}

    <p class="text-muted mb-3" style="font-size:.88rem;text-align:center">
        Inserisci il codice a 6 cifre dall'app Authenticator.
    </p>

    <form method="post" action="{$form_action|escape:'html'}" id="totp-form">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="submitMfaVerify" value="1">
        <input type="hidden" name="code" id="otp-hidden">

        <div class="otp-wrap" id="otp-wrap">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" pattern="[0-9]" tabindex="1">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" pattern="[0-9]" tabindex="2">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" pattern="[0-9]" tabindex="3">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" pattern="[0-9]" tabindex="4">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" pattern="[0-9]" tabindex="5">
            <input class="otp-digit" type="text" inputmode="numeric" maxlength="1" autocomplete="off" pattern="[0-9]" tabindex="6">
        </div>

        <button type="submit" class="btn btn-verify btn-block" id="btn-verify">
            <i class="material-icons mr-1" style="font-size:1.05rem;vertical-align:middle">check_circle</i>
            Verifica e accedi
        </button>
    </form>

    {if $has_passkeys}
        <div class="mfa-divider">oppure</div>
        <button id="passkey-auth-btn" class="btn btn-passkey btn-block">
            <i class="material-icons mr-1" style="font-size:1.15rem;vertical-align:middle">fingerprint</i>
            Accedi con Passkey
        </button>
        <div id="passkey-auth-status" class="small mt-2 text-center text-muted"></div>
    {/if}

    <div class="mfa-divider">oppure</div>

    <div class="text-center">
        <a href="{$recover_url|escape:'html'}" class="btn-recovery">
            <i class="material-icons" style="font-size:.85rem;vertical-align:middle;margin-right:.2rem">vpn_key</i>
            Usa un codice di recupero
        </a>
    </div>
{/block}

{block name=scripts}
    <script>
        (function () {
            var digits = Array.from(document.querySelectorAll('.otp-digit'));
            var hidden = document.getElementById('otp-hidden');
            var form   = document.getElementById('totp-form');

            function syncHidden() {
                hidden.value = digits.map(function (d) { return d.value; }).join('');
                digits.forEach(function (d) {
                    d.classList.toggle('is-filled', d.value !== '');
                });
                return /^\d{6}$/.test(hidden.value);
            }

            function focusIndex(i) {
                if (i >= 0 && i < digits.length) { digits[i].focus(); }
            }

            form.addEventListener('submit', function (e) {
                if (!syncHidden()) { e.preventDefault(); digits[0].focus(); }
            });

            digits.forEach(function (input, idx) {
                input.addEventListener('focus', function () { this.select(); });
                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace') {
                        if (this.value === '' && idx > 0) { digits[idx - 1].value = ''; focusIndex(idx - 1); }
                        else { this.value = ''; }
                        syncHidden(); e.preventDefault(); return;
                    }
                    if (e.key === 'ArrowLeft')  { focusIndex(idx - 1); e.preventDefault(); return; }
                    if (e.key === 'ArrowRight') { focusIndex(idx + 1); e.preventDefault(); return; }
                });
                input.addEventListener('input', function () {
                    var val = this.value.replace(/\D/g, '');
                    if (val.length > 1) {
                        val.split('').forEach(function (ch, offset) {
                            if (idx + offset < digits.length) { digits[idx + offset].value = ch; }
                        });
                        focusIndex(Math.min(idx + val.length, digits.length - 1));
                    } else {
                        this.value = val;
                        if (val !== '') { focusIndex(idx + 1); }
                    }
                    if (syncHidden()) { form.submit(); }
                });
            });

            document.getElementById('otp-wrap').addEventListener('paste', function (e) {
                e.preventDefault();
                var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                text.split('').slice(0, 6).forEach(function (ch, i) { digits[i].value = ch; });
                focusIndex(Math.min(text.length, 5));
                if (syncHidden()) { form.submit(); }
            });

            digits[0].focus();
        })();

        window.__PASSKEY_OPTIONS_URL__ = '{$passkey_ajax_url|escape:'javascript'}&action=authOptions';
        window.__PASSKEY_VERIFY_URL__  = '{$passkey_ajax_url|escape:'javascript'}&action=authVerify';
    </script>
    {if $has_passkeys}
        <script src="{$module_dir}views/js/passkey-auth.js"></script>
    {/if}
{/block}
