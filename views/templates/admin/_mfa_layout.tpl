<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{block name=page_title}MFA{/block} — {$shop_name|escape:'html'}</title>
    <link rel="stylesheet" href="{$admin_theme_url}theme.css">
    <style>
        html { overflow-y: auto; }
        body.mfa-page {
            background: #f0f2f5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            padding: 2.5rem 1rem 2rem;
        }
        @media (min-height: 640px) {
            body.mfa-page { justify-content: center; }
        }
        .mfa-logo-wrap {
            text-align: center;
            margin-bottom: 1.75rem;
        }
        .mfa-logo-wrap img {
            max-height: 64px;
            max-width: 220px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
        }
        .mfa-logo-text {
            font-size: 1.3rem;
            font-weight: 700;
            color: #363a41;
            letter-spacing: -.01em;
        }
        .mfa-card-wrapper {
            width: 100%;
            max-width: {block name=card_max_width}460px{/block};
        }
        .mfa-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 20px rgba(0,0,0,.11), 0 1px 4px rgba(0,0,0,.06);
            overflow: hidden;
        }
        .mfa-card-header {
            background: #363a41;
            color: #fff;
            padding: 1rem 1.5rem;
            font-size: .9rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: .5rem;
            letter-spacing: .01em;
        }
        .mfa-card-header .material-icons {
            font-size: 1.2rem;
            color: #25b9d7;
        }
        .mfa-card-body {
            padding: 1.75rem 2rem;
        }
        .mfa-divider {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin: 1.1rem 0;
            color: #adb5bd;
            font-size: .76rem;
            text-transform: uppercase;
            letter-spacing: .08em;
        }
        .mfa-divider::before,
        .mfa-divider::after {
            content: '';
            flex: 1;
            border-top: 1px solid #e9ecef;
        }
        .mfa-footer {
            margin-top: 1.5rem;
            color: #adb5bd;
            font-size: .76rem;
            text-align: center;
        }
        .btn-primary {
            background-color: #25b9d7;
            border-color: #25b9d7;
        }
        .btn-primary:hover,
        .btn-primary:active,
        .btn-primary:focus {
            background-color: #1fa8c4;
            border-color: #1fa8c4;
        }
        .form-control:focus {
            border-color: #25b9d7;
            box-shadow: 0 0 0 .2rem rgba(37,185,215,.2);
        }
        a { color: #25b9d7; }
        a:hover { color: #1fa8c4; }
        {block name=extra_styles}{/block}
    </style>
</head>
<body class="mfa-page">

<div class="mfa-logo-wrap">
    {if $shop_logo_url}
        <img src="{$shop_logo_url|escape:'html'}" alt="{$shop_name|escape:'html'}">
    {else}
        <div class="mfa-logo-text">{$shop_name|escape:'html'}</div>
    {/if}
</div>

<div class="mfa-card-wrapper">
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