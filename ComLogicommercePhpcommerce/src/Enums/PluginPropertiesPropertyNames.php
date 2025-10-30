<?php

namespace Plugins\ComLogicommercePhpcommerce\Enums;

use SDK\Core\Enums\Enum;

/**
 * This is the PluginDataActions enumeration class.
 * This class declares actions enumerations.
 * <br> This class extends SDK\Core\Enums\Enum, see this class.
 *
 * @abstract
 *
 * @see PluginPropertiesPropertyNames::CLEANCACHEPATH
 * @see PluginPropertiesPropertyNames::ERRORONCACHEABLEZEROTTL
 * @see PluginPropertiesPropertyNames::LIFETIMECACHEOBJECTAPPLICATION
 * @see PluginPropertiesPropertyNames::LIFETIMECACHEOBJECTLCFWK
 * @see PluginPropertiesPropertyNames::LIFETIMECACHEOBJECTPLUGINS
 * @see PluginPropertiesPropertyNames::LIFETIMECACHEOBJECTS
 * @see PluginPropertiesPropertyNames::LIFETIMESESSION
 * @see PluginPropertiesPropertyNames::LOGENABLED
 * @see PluginPropertiesPropertyNames::LOGHANDLER
 * @see PluginPropertiesPropertyNames::LOGLEVEL
 * @see PluginPropertiesPropertyNames::LOGGERCONNECTIONENABLED
 * @see PluginPropertiesPropertyNames::LOGGERDEBUGINFOENABLED
 * @see PluginPropertiesPropertyNames::LOGGEREXCEPTIONENABLED
 * @see PluginPropertiesPropertyNames::LOGGERHEALTHCHECKENABLED
 * @see PluginPropertiesPropertyNames::LOGGERLEVEL
 * @see PluginPropertiesPropertyNames::LOGGERREQUESTHANDLERENABLED
 * @see PluginPropertiesPropertyNames::LOGGERTIMERENABLED
 * @see PluginPropertiesPropertyNames::MAINTENANCE
 * @see PluginPropertiesPropertyNames::MAINTENANCEALLOWIPS
 * @see PluginPropertiesPropertyNames::PHPCOMMERCETOKEN
 * @see PluginPropertiesPropertyNames::TWIGOPTIONAUTORELOAD
 * @see PluginPropertiesPropertyNames::TWIGOPTIONCACHE
 * @see PluginPropertiesPropertyNames::TWIGOPTIONOPTIMIZATIONS
 * @see PluginPropertiesPropertyNames::TWIGOPTIONSTRICTVARIABLES
 * @see PluginPropertiesPropertyNames::USECACHEREDISSESSION
 * @see PluginPropertiesPropertyNames::USECACHEREDISOBJECT
 * @see PluginPropertiesPropertyNames::LOGINREQUIRED
 *
 * @see Enum
 *
 * @package Plugins\ComLogicommercePhpcommerce\Enums;
 */
class PluginPropertiesPropertyNames extends Enum {

    public const CLEANCACHEPATH = 'cleanCachePath';

    public const ERRORONCACHEABLEZEROTTL = 'errorOnCacheableZeroTTL';

    public const LIFETIMECACHEOBJECTAPPLICATION = 'lifeTimeCacheObjectApplication';

    public const LIFETIMECACHEOBJECTLCFWK = 'lifeTimeCacheObjectLcFWK';

    public const LIFETIMECACHEOBJECTPLUGINS = 'lifeTimeCacheObjectPlugins';

    public const LIFETIMECACHEOBJECTS = 'lifeTimeCacheObjects';

    public const LIFETIMESESSION = 'lifeTimeSession';

    public const LOGENABLED = 'logEnabled';

    public const LOGHANDLER = 'logHandler';

    public const LOGLEVEL = 'logLevel';

    public const LOGGERCONNECTIONENABLED = 'loggerConnectionEnabled';

    public const LOGGERDEBUGINFOENABLED = 'loggerDebugInfoEnabled';

    public const LOGGEREXCEPTIONENABLED = 'loggerExceptionEnabled';

    public const LOGGERHEALTHCHECKENABLED = 'loggerHealthcheckEnabled';

    public const LOGGERLEVEL = 'loggerLevel';

    public const LOGGERREQUESTHANDLERENABLED = 'loggerRequestHandlerEnabled';

    public const LOGGERTIMERENABLED = 'loggerTimerEnabled';

    public const LOGINREQUIRED = 'loginRequired';

    public const MAINTENANCE = 'maintenance';

    public const MAINTENANCEALLOWIPS = 'maintenanceAllowIps';

    public const PHPCOMMERCETOKEN = 'phpCommerceToken';

    public const TWIGDEVEL = 'twigDevel';

    public const TWIGOPTIONAUTORELOAD = 'twigOptionAutoreload';

    public const TWIGOPTIONCACHE = 'twigOptionCache';

    public const TWIGOPTIONOPTIMIZATIONS = 'twigOptionOptimizations';

    public const TWIGOPTIONSTRICTVARIABLES = 'twigOptionStrictVariables';

    public const USECACHEREDISSESSION = 'useCacheRedisSession';

    public const USECACHEREDISOBJECT = 'useCacheRedisObject';
}
