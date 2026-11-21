<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 1.0.1: il gate MFA non veniva eseguito per gli employee non SuperAdmin (permesso "view"
 * sul modulo mancante per il loro profilo, vedi Mfaadmin::grantModuleViewToProfiles()).
 */
function upgrade_module_1_0_1($module)
{
    return $module->registerHook('actionObjectProfileAddAfter')
        && $module->grantModuleViewToProfiles();
}
