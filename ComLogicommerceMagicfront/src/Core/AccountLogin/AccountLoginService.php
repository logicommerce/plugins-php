<?php

declare(strict_types=1);

namespace Plugins\ComLogicommerceMagicfront\Core\AccountLogin;

use FWK\Core\Resources\Loader;
use FWK\Core\Resources\Utils;
use FWK\Core\Theme\Theme;
use FWK\Enums\Services;
use Plugins\ComLogicommerceMagicfront\Core\Providers\WidgetDataService;
use Plugins\ComLogicommerceMagicfront\Core\Resources\MagicfrontLogger;
use Plugins\ComLogicommerceMagicfront\Dtos\Catalog\Page\Page as PluginPage;

/**
 * OAUTH plugins for the `accountLogin` widget's social sign-in block.
 *
 * Social login is plugin territory: FWK only loops {@see \FWK\Services\PluginService::getOauthPlugins()}
 * and includes a partial each plugin ships at
 * `twigCoreTemplates/plugins/<Module>/core/macros/modes/<coreMode>/user/loginFormOauth.html.twig`
 * (themes/core/macros/modes/bootstrap5/user/loginForm.html.twig). The markup differs per plugin — Google
 * and Facebook paint a static anchor, Amazonpay reads its own properties and emits a script — so the
 * widget cannot carry it: it resolves the partial the same way the store does.
 *
 * What this carrier adds over the store's `{% include ... ignore missing %}` is the missing-template
 * check. `ignore missing` swallows a partial that is not there, and a commerce running a coreMode a
 * plugin never shipped (Googleconnect ships bootstrap5 only, Facebookconnect bootstrap3 and bootstrap5)
 * would lose its sign-in button with no signal at all. A plugin the commerce has ENABLED for oauth and
 * whose partial cannot be found is a misconfiguration, so it is logged ({@see MagicfrontLogger}) rather than rendered as nothing.
 *
 * @package Plugins\ComLogicommerceMagicfront\Core\AccountLogin
 */
class AccountLoginService implements WidgetDataService {

    /** Path of the per-plugin partial, relative to that plugin's registered twigCoreTemplates root. */
    private const TEMPLATE = 'plugins/%s/core/macros/modes/%s/user/loginFormOauth.html.twig';

    /**
     * @return array
     */
    public function fetchForWidget(?PluginPage $widget): array {
        $plugins = Loader::service(Services::PLUGIN)->getOauthPlugins();
        if ($plugins === null) {
            return [];
        }
        $coreMode = Theme::getInstance()->getMode();
        $oauth    = [];
        foreach ($plugins->getItems() as $plugin) {
            $directory = Utils::getCamelFromSnake($plugin->getModule(), '.');
            $template  = sprintf(self::TEMPLATE, $directory, $coreMode);
            $file      = PLUGINS_LOAD_PATH . '/' . $directory . '/twigCoreTemplates/' . $template;
            if (!is_file($file)) {
                MagicfrontLogger::record('warning', MagicfrontLogger::OAUTH_TEMPLATE_MISSING, sprintf(
                    'oauth plugin %s is enabled but ships no %s template',
                    $plugin->getModule(),
                    $coreMode
                ), ['widgetType' => 'accountLogin', 'reason' => $template]);
                continue;
            }
            $oauth[] = ['template' => $template, 'plugin' => $plugin];
        }
        return ['oauthPlugins' => $oauth, 'coreMode' => $coreMode];
    }
}
