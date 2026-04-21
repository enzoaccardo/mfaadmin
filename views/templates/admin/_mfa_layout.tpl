<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{block name=page_title}MFA{/block} — {$shop_name|escape:'html'}</title>
    <link rel="stylesheet" href="{$admin_theme_url}theme.css">
    <link rel="stylesheet" href="{$module_dir}views/css/mfa-standalone.css">
</head>
<body class="mfa-page">

<div class="mfa-logo-wrap">
    {if $shop_logo_url}
        <img src="{$shop_logo_url|escape:'html'}" alt="{$shop_name|escape:'html'}">
    {else}
        <div class="mfa-logo-text">{$shop_name|escape:'html'}</div>
    {/if}
</div>

<div class="mfa-card-wrapper" style="max-width:{$card_max_width|default:'460px'}">
    <div class="mfa-card">
        <div class="mfa-card-header">
            <i class="material-icons">{block name=card_icon}lock{/block}</i>
            <span>{block name=card_title}{/block}</span>
        </div>
        <div class="mfa-card-body">
            {block name=content}{/block}
        </div>
    </div>
</div>

<div class="mfa-footer">{$shop_name|escape:'html'} — Area amministrazione</div>

{block name=scripts}{/block}
</body>
</html>
