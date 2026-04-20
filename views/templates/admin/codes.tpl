{extends file='_mfa_layout.tpl'}

{block name=page_title}Salva i codici di recupero{/block}
{block name=card_icon}assignment_turned_in{/block}
{block name=card_title}Salva i codici di recupero{/block}
{block name=card_max_width}540px{/block}

{block name=extra_styles}
    .codes-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: .5rem;
        margin: 1rem 0 1.5rem;
    }
    .code-item {
        font-family: 'SFMono-Regular', 'Courier New', monospace;
        font-size: .9rem;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        padding: .5rem .75rem;
        text-align: center;
        letter-spacing: .06em;
        user-select: all;
    }
{/block}

{block name=content}
    <div class="alert alert-warning py-2 mb-3" style="font-size:.85rem">
        <i class="material-icons" style="font-size:1rem;vertical-align:middle;margin-right:.25rem">warning</i>
        <strong>Importante:</strong> questi codici saranno mostrati <strong>una sola volta</strong>.
        Salvali in un posto sicuro — servono se perdi l'accesso all'app Authenticator.
    </div>

    <div class="codes-grid">
        {foreach $recovery_codes as $code}
            <div class="code-item">{$code|escape:'html'}</div>
        {/foreach}
    </div>

    <form method="post" action="{$form_action|escape:'html'}">
        <input type="hidden" name="token" value="{$token}">
        <input type="hidden" name="submitMfaCodesConfirm" value="1">

        <button type="submit" class="btn btn-primary btn-block">
            <i class="material-icons mr-1" style="font-size:1.1rem;vertical-align:middle">check</i>
            Ho salvato i codici, continua
        </button>
    </form>
{/block}