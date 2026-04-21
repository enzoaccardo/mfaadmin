{extends file='_mfa_layout.tpl'}

{block name=page_title}Configura autenticazione a due fattori{/block}
{block name=card_icon}smartphone{/block}
{block name=card_title}Configura autenticazione a due fattori{/block}

{block name=content}
    {if $mfa_error}
        <div class="alert alert-danger py-2 mb-3" style="font-size:.88rem">
            <i class="material-icons" style="font-size:1rem;vertical-align:middle;margin-right:.25rem">error_outline</i>
            {$mfa_error|escape:'html'}
        </div>
    {/if}

    <p class="text-muted mb-3" style="font-size:.9rem;text-align:center">
        Scansiona il QR code con Google Authenticator, Authy o un'altra app TOTP compatibile.
    </p>

    <div class="qr-wrap-outer">
        <div class="qr-wrap">{$qr_svg}</div>
    </div>

    <p class="text-muted small text-center mb-1">Non riesci a scansionare? Inserisci questo codice manualmente:</p>
    <div class="secret-badge mb-4">{$mfa_secret|escape:'html'}</div>

    <form method="post" action="{$form_action|escape:'html'}" id="setup-form">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="submitMfaSetup" value="1">

        <p class="text-muted mb-2" style="font-size:.88rem;text-align:center">
            Conferma con il codice a 6 cifre
        </p>

        <input
            type="text"
            name="code"
            id="otp-code"
            class="otp-single"
            inputmode="numeric"
            maxlength="6"
            autocomplete="one-time-code"
            placeholder="000000"
            autofocus
        >

        <button type="submit" class="btn btn-activate btn-block">
            <i class="material-icons mr-1" style="font-size:1.05rem;vertical-align:middle">check_circle</i>
            Attiva MFA
        </button>
    </form>
{/block}

{block name=scripts}
    <script>
        (function () {
            var input = document.getElementById('otp-code');
            var form  = document.getElementById('setup-form');
            input.addEventListener('input', function () {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
                if (this.value.length === 6) { form.submit(); }
            });
        })();
    </script>
{/block}
