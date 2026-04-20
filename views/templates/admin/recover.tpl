{extends file='_mfa_layout.tpl'}

{block name=page_title}Codice di recupero{/block}
{block name=card_icon}vpn_key{/block}
{block name=card_title}Codice di recupero{/block}
{block name=card_max_width}520px{/block}

{block name=extra_styles}
    .rc-groups {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: .45rem;
        margin-bottom: 1.5rem;
    }
    .rc-group {
        display: flex;
        gap: .35rem;
    }
    .rc-sep {
        font-size: 1.6rem;
        font-weight: 300;
        color: #adb5bd;
        line-height: 1;
        margin: 0 .05rem;
        user-select: none;
    }
    .rc-digit {
        width: 46px;
        height: 56px;
        font-size: 1.25rem;
        font-weight: 700;
        text-align: center;
        border: 2px solid #dee2e6;
        border-radius: 8px;
        outline: none;
        transition: border-color .15s, box-shadow .15s;
        caret-color: transparent;
        font-family: 'SFMono-Regular', 'Courier New', monospace;
        color: #363a41;
        text-transform: uppercase;
    }
    .rc-digit:focus {
        border-color: #25b9d7;
        box-shadow: 0 0 0 .2rem rgba(37,185,215,.2);
    }
    .rc-digit.is-filled {
        border-color: #25b9d7;
        background: #f0fbfe;
    }
    @media (max-width: 420px) {
        .rc-digit { width: 36px; height: 46px; font-size: 1rem; }
        .rc-groups { gap: .3rem; }
        .rc-group  { gap: .25rem; }
        .rc-sep    { font-size: 1.3rem; }
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
        Inserisci uno dei codici di recupero salvati al momento del setup.
    </p>

    <form method="post" action="{$form_action|escape:'html'}" id="rc-form">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="submitMfaRecover" value="1">
        <input type="hidden" name="recovery_code" id="rc-hidden">

        <div class="rc-groups" id="rc-groups">
            <div class="rc-group" data-group="0">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="1">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="2">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="3">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="4">
            </div>
            <span class="rc-sep">–</span>
            <div class="rc-group" data-group="1">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="5">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="6">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="7">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="8">
            </div>
            <span class="rc-sep">–</span>
            <div class="rc-group" data-group="2">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="9">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="10">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="11">
                <input class="rc-digit" type="text" inputmode="text" maxlength="1" autocomplete="off" tabindex="12">
            </div>
        </div>

        <button type="submit" class="btn btn-verify btn-block" id="btn-rc" disabled>
            <i class="material-icons mr-1" style="font-size:1.05rem;vertical-align:middle">login</i>
            Accedi
        </button>
    </form>

    <div class="mfa-divider">oppure</div>

    <div class="text-center">
        <a href="{$verify_url|escape:'html'}" class="btn-recovery">
            <i class="material-icons" style="font-size:.85rem;vertical-align:middle;margin-right:.2rem">arrow_back</i>
            Torna alla verifica TOTP
        </a>
    </div>
{/block}

{block name=scripts}
    <script>
        (function () {
            var HEX = /^[0-9A-Fa-f]$/;
            var digits  = Array.from(document.querySelectorAll('.rc-digit'));
            var hidden  = document.getElementById('rc-hidden');
            var form    = document.getElementById('rc-form');
            var btnRc   = document.getElementById('btn-rc');

            function updateHidden() {
                var groups = [
                    digits.slice(0, 4).map(function (d) { return d.value.toUpperCase(); }).join(''),
                    digits.slice(4, 8).map(function (d) { return d.value.toUpperCase(); }).join(''),
                    digits.slice(8, 12).map(function (d) { return d.value.toUpperCase(); }).join(''),
                ];
                hidden.value = groups.join('-');
                var complete = groups.every(function (g) { return g.length === 4; });
                btnRc.disabled = !complete;
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

                input.addEventListener('input', function () {
                    var raw = this.value.replace(/[^0-9A-Fa-f]/g, '').toUpperCase();
                    if (raw.length > 1) {
                        // Distribuzione automatica di più caratteri (es. incolla in un box)
                        raw.split('').forEach(function (ch, offset) {
                            if (idx + offset < digits.length) {
                                digits[idx + offset].value = ch;
                            }
                        });
                        focusIndex(Math.min(idx + raw.length, digits.length - 1));
                    } else {
                        this.value = raw;
                        if (raw !== '') { focusIndex(idx + 1); }
                    }
                    updateHidden();
                });
            });

            // Incolla sull'intero contenitore: accetta XXXX-XXXX-XXXX o XXXXXXXXXXXX
            document.getElementById('rc-groups').addEventListener('paste', function (e) {
                e.preventDefault();
                var text = (e.clipboardData || window.clipboardData)
                    .getData('text')
                    .replace(/-/g, '')
                    .replace(/[^0-9A-Fa-f]/g, '')
                    .toUpperCase();
                text.split('').slice(0, 12).forEach(function (ch, i) {
                    digits[i].value = ch;
                });
                focusIndex(Math.min(text.length, 11));
                updateHidden();
            });

            digits[0].focus();
        })();
    </script>
{/block}