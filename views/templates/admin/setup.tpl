{extends file='_mfa_layout.tpl'}

{block name=page_title}Configura autenticazione a due fattori{/block}
{block name=card_icon}smartphone{/block}
{block name=card_title}Configura autenticazione a due fattori{/block}
{block name=card_max_width}520px{/block}

{block name=extra_styles}
    .qr-wrap {
        display: inline-flex;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 8px;
        padding: 10px;
    }
    .qr-wrap svg { width: 176px; height: 176px; display: block; }
    .secret-badge {
        font-family: 'SFMono-Regular', 'Courier New', monospace;
        font-size: .85rem;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: .5rem 1rem;
        letter-spacing: .12em;
        word-break: break-all;
        text-align: center;
        user-select: all;
    }
{/block}

{block name=content}
    {if $mfa_error}
        <div class="alert alert-danger py-2 mb-3" style="font-size:.88rem">
            <i class="material-icons" style="font-size:1rem;vertical-align:middle;margin-right:.25rem">error_outline</i>
            {$mfa_error|escape:'html'}
        </div>
    {/if}

    <p class="text-muted mb-3" style="font-size:.9rem">
        Scansiona il QR code con Google Authenticator, Authy o un'altra app TOTP compatibile.
    </p>

    <div class="text-center mb-3">
        <div class="qr-wrap">{$qr_svg}</div>
    </div>

    <p class="text-muted small text-center mb-1">Non riesci a scansionare? Inserisci questo codice manualmente:</p>
    <div class="secret-badge mb-4">{$mfa_secret|escape:'html'}</div>

    <form method="post" action="{$form_action|escape:'html'}">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="submitMfaSetup" value="1">

        <div class="form-group mb-4">
            <label class="font-weight-bold" style="font-size:.85rem;text-transform:uppercase;letter-spacing:.04em;color:#363a41">
                Conferma con il codice a 6 cifre
            </label>
            <input type="text" name="code"
                   class="form-control form-control-lg text-center"
                   style="font-size:1.6rem;letter-spacing:.3em;font-family:'SFMono-Regular','Courier New',monospace"
                   placeholder="000000"
                   maxlength="6"
                   autocomplete="one-time-code"
                   inputmode="numeric"
                   pattern="[0-9]{ldelim}6{rdelim}"
                   autofocus
                   required>
            <small class="form-text text-muted">Inserisci il codice mostrato dall'app per completare la configurazione.</small>
        </div>

        <button type="submit" class="btn btn-primary btn-block btn-lg">
            <i class="material-icons mr-1" style="font-size:1.1rem;vertical-align:middle">check_circle</i>
            Attiva MFA
        </button>
    </form>
{/block}