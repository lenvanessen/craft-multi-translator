<?php

namespace digitalpulsebe\craftmultitranslator;

use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin;
use craft\elements\Entry;
use craft\events\DefineHtmlEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\services\UserPermissions;
use craft\web\twig\variables\CraftVariable;
use digitalpulsebe\craftmultitranslator\elements\actions\Copy;
use digitalpulsebe\craftmultitranslator\elements\actions\Translate;
use digitalpulsebe\craftmultitranslator\models\Settings;
use digitalpulsebe\craftmultitranslator\services\DeeplService;
use digitalpulsebe\craftmultitranslator\services\TranslateService;
use digitalpulsebe\craftmultitranslator\variables\Variable;
use yii\base\Event;

/**
 * Multi Translator plugin
 *
 * @method static MultiTranslator getInstance()
 * @method Settings getSettings()
 * @property DeeplService $deepl
 * @property TranslateService $translate
 * @author Digital Pulse nv <support@digitalpulse.be>
 * @copyright Digital Pulse nv
 * @license https://craftcms.github.io/license/ Craft License
 */
class MultiTranslator extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public ?string $name = 'Multi Translator';

    public static function config(): array
    {
        return [
            'components' => [
                'deepl' => DeeplService::class,
                'translate' => TranslateService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->registerVariables();
            $this->registerSidebarHtml();
            $this->registerPermissions();
            $this->registerActions();
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('multi-translator/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function registerVariables(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            function (Event $event) {
                /** @var CraftVariable $variable */
                $variable = $event->sender;
                $variable->set('multiTranslator', Variable::class);

            }
        );
    }

    private function registerSidebarHtml(): void
    {
        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event) {
                if ($event->sender instanceof Entry) {
                    $template = Craft::$app->getView()->renderTemplate('multi-translator/_sidebar/buttons', [
                        "element" => $event->sender,
                        "plugin" => $this
                    ]);
                    $event->html .= $template;
                }
            }
        );
    }

    private function registerActions(): void
    {
        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_ACTIONS,
            function(RegisterElementActionsEvent $event) {
                $defaultSiteHandle = Craft::$app->sites->currentSite->handle;
                $sourceSiteHandle = Craft::$app->request->getParam('site', $defaultSiteHandle);

                if (Craft::$app->user->checkPermission('multiTranslateContent')) {
                    $event->actions[] = [
                        'type' => Translate::class,
                        'sourceSiteHandle' => $sourceSiteHandle
                    ];
                }
                if (Craft::$app->user->checkPermission('multiCopyContent')) {
                    $event->actions[] = [
                        'type' => Copy::class,
                        'sourceSiteHandle' => $sourceSiteHandle
                    ];
                }
            }
        );
    }

    /**
     * Register custom permission
     *
     * @return void
     */
    private function registerPermissions(): void
    {
        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => 'DeepL Translator',
                    'permissions' => [
                        'multiTranslateContent' => [
                            'label' => 'Translate Content',
                        ],
                        'multiCopyContent' => [
                            'label' => 'Copy Content',
                        ],
                    ],
                ];
            }
        );
    }
}
