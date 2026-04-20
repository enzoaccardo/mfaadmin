{extends file='_mfa_layout.tpl'}

{block name=page_title}Verifica identità{/block}
{block name=card_icon}security{/block}
{block name=card_title}Verifica identità{/block}
{block name=card_max_width}500px{/block}

{block name=extra_styles}
    .otp-wrap {
        display: flex;
        gap: .6rem;
        justify-content: center;
        margin-bottom: 1.5rem;
    }
    .otp-digit {
        width: 52px;
        height: 62px;
        font-size: 1.75rem;
        font-weight: 700;
        text-align: center;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
        caret-color: transparent;
        font-family: 'SFMono-Regular', 'Courier New', monospace;
        color: #363a41;
    }
    .otp-digit:focus {
        border-color: #25b9d7;
        box-shadow: 0 0 0 .2rem rgba(37,185,215,.2);
    }
    .otp-digit.is-filled {
        border-color: #25b9d7;
        background: #f0fbfe;
    }
    @media (max-width: 400px) {
        .otp-digit { width: 42px; height: 52px; font-size: 1.4rem; }
        .otp-wrap  { gap: .4rem; }
    }
    .btn-verify {
        background: linear-gradient(135deg, #25b9d7 0%, #1a9dba 100%);
        border: none;
        color: #fff;
        border-radius: 8px;
        padding: .7rem 1.25rem;
        font-size: .95rem;
        font-weight: 600;
        letter-spacing: .02em;
        transition: opacity .15s, transform .1s;
        box-shadow: 0 3px 10px rgba(37,185,215,.35);
    }
    .btn-verify:hover  { opacity: .92; transform: translateY(-1px); color: #fff; }
    .btn-verify:active { opacity: 1;   transform: translateY(0);    color: #fff; }
    .btn-verify:disabled { opacity: .6; transform: none; cursor: not-allowed; }
    .btn-passkey {
        border: 2px solid #dee2e6;
        background: #fff;
        color: #363a41;
        border-radius: 8px;
        padding: .6rem 1.25rem;
        font-size: .9rem;
        font-weight: 500;
        transition: border-color .15s, box-shadow .15s;
    }
    .btn-passkey:hover {
        border-color: #25b9d7;
        box-shadow: 0 0 0 .15rem rgba(37,185,215,.15);
        color: #1a9dba;
    }
    .btn-recovery {
        background: none;
        border: none;
        color: #9099a2;
        font-size: .82rem;
        padding: .35rem .5rem;
        cursor: pointer;
        text-decoration: none;
        transition: color .15s;
    }
    .btn-recovery:hover { color: #25b9d7; text-decoration: none; }
{/block}

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

        <button type="submit" class="btn btn-verify btn-block" id="btn-verify" disabled>
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
            var digits  = Array.from(document.querySelectorAll('.otp-digit'));
            var hidden  = document.getElementById('otp-hidden');
            var form    = document.getElementById('totp-form');
            var btnVerify = document.getElementById('btn-verify');

            function updateHidden() {
                var val = digits.map(function (d) { return d.value; }).join('');
                hidden.value = val;
                var complete = val.length === 6 && /^\d{6}$/.test(val);
                btnVerify.disabled = !complete;
                digits.forEach(function (d) {
                    d.classList.toggle('is-filled', d.value !== '');
                });
                return complete;
            }

            function focusIndex(i) {
                if (i >= 0 && i < digits.length) { digits[i].focus(); }
            }

            digits.forEach(function (input, idx) {
                input.addEventListener('focus', function () { this.select(); });

                input.addEventListener('keydown', function (e) {
                    if (e.key === 'Backspace') {
                        if (this.value === '' && idx > 0) {
                            digits[idx - 1].value = '';
                            focusIndex(idx - 1);
                        } else {
                            this.value = '';
                        }
                        updateHidden();
                        e.preventDefault();
                        return;
                    }
                    if (e.key === 'ArrowLeft')  { focusIndex(idx - 1); e.preventDefault(); return; }
                    if (e.key === 'ArrowRight') { focusIndex(idx + 1); e.preventDefault(); return; }
                });

                input.addEventListener('input', function (e) {
                    var val = this.value.replace(/\D/g, '');
                    if (val.length > 1) {
                        // Gestisce incolla di più cifre in un singolo box
                        var chars = val.split('');
                        chars.forEach(function (ch, offset) {
                            if (idx + offset < digits.length) {
                                digits[idx + offset].value = ch;
                            }
                        });
                        var next = idx + chars.length;
                        focusIndex(Math.min(next, digits.length - 1));
                    } else {
                        this.value = val;
                        if (val !== '') { focusIndex(idx + 1); }
                    }
                    if (updateHidden()) { form.submit(); }
                });
            });

            // Gestisce incolla sull'intero form
            document.getElementById('otp-wrap').addEventListener('paste', function (e) {
                e.preventDefault();
                var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
                text.split('').slice(0, 6).forEach(function (ch, i) {
                    digits[i].value = ch;
                });
                focusIndex(Math.min(text.length, 5));
                if (updateHidden()) { form.submit(); }
            });

            // Primo box autofocus
            digits[0].focus();
        })();

        window.__PASSKEY_OPTIONS_URL__ = '{$passkey_ajax_url|escape:'javascript'}&action=authOptions';
        window.__PASSKEY_VERIFY_URL__  = '{$passkey_ajax_url|escape:'javascript'}&action=authVerify';
    </script>
    {if $has_passkeys}
        <script src="{$module_dir}views/js/passkey-auth.js"></script>
    {/if}
{/block}