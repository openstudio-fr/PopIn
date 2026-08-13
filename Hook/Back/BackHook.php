<?php

namespace PopIn\Hook\Back;

use PopIn\Model\PopInCampaign;
use PopIn\Model\PopInCampaignQuery;
use PopIn\PopIn;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Event\Hook\HookRenderBlockEvent;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\HttpFoundation\Request;
use Thelia\Core\HttpFoundation\Session\Session;
use Thelia\Core\Hook\BaseHook;
use Thelia\Model\ConfigQuery;
use Thelia\Model\ContentQuery;
use Thelia\Model\Lang;
use Thelia\Model\LangQuery;
use Thelia\Tools\URL;

/**
 * Back-office hooks.
 *
 * Migration Thelia 3 : ce service est declare dans Config/config.xml (hors perimetre de cette
 * migration) sur `<hook id="popin.hook.back" class="PopIn\Hook\Back\BackHook">`, sans liste
 * d'<argument>, donc sans autowiring (voir commentaires similaires dans StripePaymentHook /
 * Carousel\Hook\BackHook). On n'ajoute donc aucun parametre de constructeur : les nouvelles
 * donnees necessaires (campagnes, langues, contenus...) sont recuperees directement via les
 * classes Propel statiques et $this->getRequest(), exactement comme le fait deja
 * PopIn\Action\PopInCampaignAction (session->getAdminEditionLang()).
 *
 * L'ecran "Pop-in campaigns" etait auparavant une page dediee (route popin.config), rendue par
 * PopIn\Controller\ConfigurationController. La route coeur `admin.module.configure`
 * (GET /admin/module/{module_code}) gagne desormais sur cette route pour l'URL
 * /admin/module/PopIn (verifie : la reponse contient data-testid="modules-configure", pas notre
 * controleur). Le coeur dispatche le hook `module.configuration` (et `module.config-js`) depuis
 * @BackOfficeDefaultTwig/module/configure.html.twig : c'est donc ce hook qui doit desormais
 * porter tout l'ecran, PopInCampaignController redirige vers cette route au lieu de rendre une
 * page dediee (voir PopInCampaignController).
 */
class BackHook extends BaseHook
{
    /**
     * Add a link to the pop-in configuration page in the tools menu.
     */
    public function onMainTopMenuTools(HookRenderBlockEvent $event): void
    {
        $event->add([
            'title' => $this->trans('Pop-in campaigns', [], PopIn::MESSAGE_DOMAIN_BO),
            'url' => URL::getInstance()->absoluteUrl('/admin/module/PopIn'),
        ]);
    }

    /**
     * Add a link to the pop-in configuration page in the tools page.
     */
    public function onToolsCol1Bottom(HookRenderEvent $event): void
    {
        $event->add($this->render('pop-in/pop-in-tools.html.twig'));
    }

    /**
     * Nouveaux hooks Thelia 3 : ces deux codes n'etaient pas utilises par PopIn en Thelia 2 (le
     * module avait sa propre route dediee). Ils sont ajoutes ici via getSubscribedHooks() (pas de
     * declaration XML pour ceux-ci) pour porter l'ecran de configuration dans le mecanisme
     * standard "Configurer le module" du coeur.
     */
    public static function getSubscribedHooks(): array
    {
        return [
            'module.configuration' => [
                ['type' => 'back', 'method' => 'onModuleConfiguration'],
            ],
            'module.config-js' => [
                ['type' => 'back', 'method' => 'onModuleConfigJs'],
            ],
        ];
    }

    public function onModuleConfiguration(HookRenderEvent $event): void
    {
        $request = $this->getRequest();
        $editLang = $this->resolveEditLang($request);
        $locale = $editLang->getLocale();

        $imageFolderId = (int) ConfigQuery::read(PopIn::CONF_KEY_IMAGE_FOLDER_ID, 0);

        $contents = [];
        foreach (ContentQuery::create()->find() as $content) {
            $content->setLocale($locale);
            $contents[] = [
                'id' => $content->getId(),
                'title' => $content->getTitle(),
            ];
        }

        $contentImages = [];
        if ($imageFolderId > 0) {
            $imageContentQuery = ContentQuery::create()
                ->useContentFolderQuery()
                    ->filterByFolderId([$imageFolderId], Criteria::IN)
                ->endUse();

            foreach ($imageContentQuery->find() as $content) {
                $content->setLocale($locale);
                $contentImages[] = [
                    'id' => $content->getId(),
                    'title' => $content->getTitle(),
                ];
            }
        }

        $campaigns = [];
        /** @var PopInCampaign $campaign */
        foreach (PopInCampaignQuery::create()->orderById()->find() as $campaign) {
            $campaign->setLocale($locale);

            $start = $campaign->getStart();
            $end = $campaign->getEnd();
            $now = new \DateTime();

            $isActive = (null === $start || $start <= $now) && (null === $end || $end >= $now);

            $campaigns[] = [
                'id' => $campaign->getId(),
                'start' => $start,
                'end' => $end,
                'is_active' => $isActive,
                'content_source_type' => $campaign->getContentSourceType(),
                'content_source_id' => $campaign->getContentSourceId(),
                'custom_title' => $campaign->getCustomTitle(),
                'custom_description' => $campaign->getCustomDescription(),
                'custom_postscriptum' => $campaign->getCustomPostscriptum(),
                'custom_link' => $campaign->getCustomLink(),
                'custom_link_text' => $campaign->getCustomLinkText(),
                'exclude_category_ids' => $campaign->getExcludeCategoryIds(),
                'exclude_folder_ids' => $campaign->getExcludeFolderIds(),
                'exclude_content_ids' => $campaign->getExcludeContentIds(),
                'exclude_home' => (bool) $campaign->getExcludeHome(),
                'exclude_url' => $campaign->getExcludeUrl(),
                'persistent' => (bool) $campaign->getPersistent(),
                // No CSRF token here: TokenProvider is not injectable into this XML-declared,
                // non-autowired hook service (see class docblock). The template appends one via
                // the `assignToken()` Twig function instead (same pattern as
                // EasyCustomerManager/EasyProductManager's delete links).
                'delete_url' => URL::getInstance()->absoluteUrl(
                    '/admin/module/PopIn/pop_in_campaign/delete',
                    ['pop_in_campaign_id' => $campaign->getId()]
                ),
            ];
        }

        $event->add($this->render('pop-in/pop-in-config.html.twig', [
            'campaigns' => $campaigns,
            'contents' => $contents,
            'content_images' => $contentImages,
            'edit_language_id' => $editLang->getId(),
            'current_locale' => $locale,
            'create_action' => URL::getInstance()->absoluteUrl('/admin/module/PopIn/pop_in_campaign'),
            'edit_action' => URL::getInstance()->absoluteUrl('/admin/module/PopIn/pop_in_campaign/edit'),
        ]));
    }

    public function onModuleConfigJs(HookRenderEvent $event): void
    {
        // The Thelia 2 screen used a jQuery bootstrap-datetimepicker plugin loaded from the T2
        // admin theme (not from this module's own assets, which never shipped those files - the
        // plugin's asset paths do not resolve through addJS()/addCSS(), which are always scoped
        // to this module's own asset directory). The DateTimeType fields already render as
        // native `<input type="datetime-local">` (Symfony's default for widget=single_text), so
        // we rely on that native picker instead of reintroducing a jQuery dependency here.
        $event->add($this->render('pop-in/pop-in-config-js.html.twig'));
    }

    /**
     * Resolve the locale used to display/edit campaign content, following the same precedence
     * as BackOfficeDefaultTwigBundle's EditLocaleResolver (query param > session > default lang).
     * Reimplemented here rather than injected: this Hook's service is XML-declared without
     * autowiring (see class docblock), so no extra constructor dependency can be added.
     */
    private function resolveEditLang(?Request $request): Lang
    {
        $session = $request?->hasSession() ? $request->getSession() : null;
        $editLanguageId = (int) $request?->query->get('edit_language_id', 0);

        if ($editLanguageId > 0 && null !== $lang = LangQuery::create()->findPk($editLanguageId)) {
            if ($session instanceof Session) {
                $session->setAdminEditionLang($lang);
            }

            return $lang;
        }

        if ($session instanceof Session) {
            return $session->getAdminEditionLang();
        }

        return Lang::getDefaultLanguage();
    }
}
