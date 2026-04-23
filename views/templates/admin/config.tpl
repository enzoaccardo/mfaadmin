{extends file="helpers/view/view.tpl"}

{block name="override_tpl"}

{if $mfa_success}
<div class="alert alert-success">{$mfa_success|escape:'html'}</div>
{/if}
{if $mfa_error}
<div class="alert alert-danger">{$mfa_error|escape:'html'}</div>
{/if}

<div class="panel">
    <div class="panel-heading">
        <i class="icon-cogs"></i> {l s='Impostazioni globali' mod='mfaadmin'}
    </div>
    <div class="panel-body">
        <form method="post" action="{$form_action|escape:'html'}">
            {* — Disabilita MFA globalmente — *}
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="mfa_disabled" value="1" {if $mfa_disabled}checked{/if}>
                        <strong style="color:#c0392b">{l s='Disabilita verifica MFA per tutti' mod='mfaadmin'}</strong>
                    </label>
                </div>
                {if $mfa_disabled}
                <div class="alert alert-warning" style="margin-top:6px">
                    <i class="icon-warning-sign"></i>
                    <strong>{l s='Attenzione: la verifica MFA è attualmente disabilitata.' mod='mfaadmin'}</strong>
                    {l s='Nessun utente viene fermato al login, indipendentemente dal proprio setup MFA.' mod='mfaadmin'}
                </div>
                {else}
                <p class="help-block">
                    {l s='Se attivo, bypassa completamente tutti i controlli MFA. Utile in emergenza o durante la manutenzione.' mod='mfaadmin'}
                </p>
                {/if}
            </div>

            <hr>

            {* — MFA obbligatorio — *}
            <div class="form-group">
                <div class="checkbox">
                    <label>
                        <input type="checkbox" name="force_mfa" value="1" {if $force_mfa}checked{/if}>
                        <strong>{l s='MFA obbligatorio per tutti gli amministratori' mod='mfaadmin'}</strong>
                    </label>
                </div>
                <p class="help-block">
                    {l s='Se attivo, ogni admin viene reindirizzato al setup MFA se non lo ha ancora configurato.' mod='mfaadmin'}
                </p>
            </div>

            <hr>

            {* — Whitelist controller (bypass MFA) — *}
            <div class="form-group">
                <label class="control-label">
                    <strong>{l s='Controller esclusi dalla verifica MFA' mod='mfaadmin'}</strong>
                </label>
                <textarea name="bypass_controllers" class="form-control" rows="5"
                          placeholder="AdminMyCronController, AdminMyWebhookController"
                >{$bypass_controllers|escape:'html'}</textarea>
                <p class="help-block">
                    {l s='Elenco di controller admin (separati da virgola) che bypassano la verifica MFA. Utile per i cron job.' mod='mfaadmin'}
                    <br>
                    {l s='I seguenti controller interni sono sempre esclusi e non vanno inseriti qui:' mod='mfaadmin'}
                    <code style="display:block;margin-top:4px;font-size:11px">{$mfa_core_controllers|escape:'html'}</code>
                </p>
            </div>

            <hr>

            {* — Email di allerta sicurezza — *}
            <div class="form-group">
                <label class="control-label">
                    <strong>{l s='Email di allerta sicurezza' mod='mfaadmin'}</strong>
                </label>
                <input type="email" name="alert_email" class="form-control" style="max-width:420px"
                       value="{$alert_email|escape:'html'}"
                       placeholder="{$alert_email_fallback|escape:'html'}">
                <p class="help-block">
                    {l s='Riceverà le notifiche di allerta MFA (avviso al 3° tentativo fallito, blocco al 5°).' mod='mfaadmin'}
                    {l s='Se non configurato, viene usata l\'email del negozio:' mod='mfaadmin'}
                    <strong>{$alert_email_fallback|escape:'html'}</strong>
                </p>
            </div>

            <button type="submit" name="submitMfaConfig" class="btn btn-primary">
                <i class="icon-save"></i> {l s='Salva impostazioni' mod='mfaadmin'}
            </button>
        </form>
    </div>
</div>

<div class="panel">
    <div class="panel-heading">
        <i class="icon-group"></i> {l s='Stato MFA per utente' mod='mfaadmin'}
    </div>
    <div class="panel-body">
        <table class="table">
            <thead>
                <tr>
                    <th>{l s='Utente' mod='mfaadmin'}</th>
                    <th>{l s='Email' mod='mfaadmin'}</th>
                    <th class="text-center">{l s='MFA attivo' mod='mfaadmin'}</th>
                    <th class="text-center">{l s='TOTP' mod='mfaadmin'}</th>
                    <th class="text-center">{l s='Passkey' mod='mfaadmin'}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            {foreach from=$employees item=emp}
                <tr>
                    <td>{$emp.firstname|escape:'html'} {$emp.lastname|escape:'html'}</td>
                    <td>{$emp.email|escape:'html'}</td>
                    <td class="text-center">
                        {if $emp.mfa_enabled}
                            <span class="label label-success"><i class="icon-check"></i> {l s='Sì' mod='mfaadmin'}</span>
                        {else}
                            <span class="label label-default"><i class="icon-times"></i> {l s='No' mod='mfaadmin'}</span>
                        {/if}
                    </td>
                    <td class="text-center">
                        {if $emp.has_secret}
                            <i class="icon-check text-success"></i>
                        {else}
                            <i class="icon-times text-muted"></i>
                        {/if}
                    </td>
                    <td class="text-center">{$emp.passkeys|intval}</td>
                    <td>
                        {if $emp.mfa_enabled || $emp.has_secret}
                        <form method="post" action="{$form_action|escape:'html'}" style="display:inline"
                              onsubmit="return confirm('{l s='Resettare MFA per questo utente? Dovrà riconfigurarlo.' mod='mfaadmin'}')">
                            <input type="hidden" name="id_employee" value="{$emp.id_employee|intval}">
                            <button type="submit" name="submitResetEmployee" class="btn btn-danger btn-xs">
                                <i class="icon-undo"></i> {l s='Reset MFA' mod='mfaadmin'}
                            </button>
                        </form>
                        {/if}
                    </td>
                </tr>
            {/foreach}
            </tbody>
        </table>
    </div>
</div>

{/block}
