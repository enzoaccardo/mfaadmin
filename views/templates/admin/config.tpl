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
