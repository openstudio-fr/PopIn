<?php

namespace PopIn\Hook\Front;

use PopIn\Model\Map\PopInCampaignTableMap;
use PopIn\Model\PopInCampaign;
use PopIn\Model\PopInCampaignQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Propel\Runtime\Map\TableMap;
use Thelia\Core\Event\Hook\HookRenderEvent;
use Thelia\Core\Hook\BaseHook;

/**
 * Front-office hooks.
 */
class FrontHook extends BaseHook
{
    /**
     * Base session key used to store if a campaign has been seen. The campaign id will be appended later.
     * @var string
     */
    protected static $BASE_SESSION_KEY_CAMPAIGN_SEEN = "popin.seen.campaign";

    /**
     * Get the currently running pop-in campaign (if any).
     * @return PopInCampaign|null
     */
    protected static function getCurrentPopInCampaign()
    {
        $now = new \DateTime();

        return PopInCampaignQuery::create()
            ->where(PopInCampaignTableMap::COL_START . Criteria::ISNULL)
            ->_or()
            ->where(PopInCampaignTableMap::COL_START . Criteria::LESS_EQUAL . '?', $now)
            ->_and()
            ->where(PopInCampaignTableMap::COL_END . Criteria::ISNULL)
            ->_or()
            ->where(PopInCampaignTableMap::COL_END . Criteria::GREATER_EQUAL . '?', $now)
            ->find();
    }

    /**
     * Get the session key used to store if a campaign has been seen.
     * @param PopInCampaign $campaign
     * @return string
     */
    public static function getSeenSessionKeyForPopInCampaign(?PopInCampaign $campaign = null, $campaignId = null)
    {
        $id = $campaign ? $campaign->getId() : $campaignId;
        return static::$BASE_SESSION_KEY_CAMPAIGN_SEEN . '.' . $id;
    }

    /**
     * Render the pop-in for the currently running campaign (if any), and mark it as seen in the client session.
     * @param HookRenderEvent $event
     */
    public function onMainBodyTop(HookRenderEvent $event)
    {
        // TODO Fix in Thelia hook called multiple times
        if (!count($this->getRequest()->attributes->all())) {
            return;
        }
        $currentCampaigns = static::getCurrentPopInCampaign();

        if (empty($currentCampaigns)) {
            return;
        }

        /** @var PopInCampaign $currentCampaign */
        foreach ($currentCampaigns as $currentCampaign) {
            if ($this->getSession()->get(static::getSeenSessionKeyForPopInCampaign($currentCampaign))) {
                continue;
            }

            $view = $this->getRequest()->attributes->get("_view");
            if ($view === "category" && ($currentCampaign->getExcludeCategoryIds() !== null || $currentCampaign->getImplicitlyExcludedCategoryIds() !== null)) {
                $excludedCategoriesIds = array_merge(
                    explode(',', $currentCampaign->getExcludeCategoryIds()),
                    explode(',', $currentCampaign->getImplicitlyExcludedCategoryIds()),
                );
                if (in_array($this->getRequest()->attributes->get('category_id'), $excludedCategoriesIds)) {
                    continue;
                }
            }
            if ($view === "product" && ($currentCampaign->getImplicitlyExcludedProductIds() !== null)) {
                $excludedProductIds = explode(',', $currentCampaign->getImplicitlyExcludedProductIds());
                if (in_array($this->getRequest()->attributes->get('product_id'), $excludedProductIds)) {
                    continue;
                }
            }
            if ($view === "folder" && ($currentCampaign->getExcludeFolderIds() !== null || $currentCampaign->getImplicitlyExcludedFolderIds() !== null)) {
                $excludedFoldersIds = array_merge(
                    explode(',', $currentCampaign->getExcludeFolderIds()),
                    explode(',', $currentCampaign->getImplicitlyExcludedFolderIds()),
                );
                if (in_array($this->getRequest()->attributes->get('folder_id'), $excludedFoldersIds)) {
                    continue;
                }
            }
            if ($view === "content" && ($currentCampaign->getExcludeContentIds() !== null || $currentCampaign->getImplicitlyExcludedContentIds() !== null)) {
                $excludedContentsIds = array_merge(
                    explode(',', $currentCampaign->getExcludeContentIds()),
                    explode(',', $currentCampaign->getImplicitlyExcludedContentIds()),
                );
                if (in_array($this->getRequest()->attributes->get('content_id'), $excludedContentsIds)) {
                    continue;
                }
            }
            if ($view === "index" && $currentCampaign->getExcludeHome()) {
                continue;
            }

            if (in_array($this->getRequest()->getPathInfo(), explode(',', $currentCampaign->getExcludeUrl()))) {
                continue;
            }

            $event->add(
                $this->render(
                    'pop-in.html',
                    [
                        'campaign' => $currentCampaign->toArray(TableMap::TYPE_FIELDNAME),
                    ]
                )
            );

            if (0 === $currentCampaign->getPersistent()) {
                $this->getSession()->set(static::getSeenSessionKeyForPopInCampaign($currentCampaign), true);
            }

            // Only display one pop in at time
            return;
        }

        return;
    }

    /**
     * Add the front-office javascript.
     * @param HookRenderEvent $event
     */
    public function onMainJavascriptInitialization(HookRenderEvent $event)
    {
        $event->add($this->addJS('assets/js/pop-in.js'));
    }

    /**
     * Add the front-office stylesheets.
     * @param HookRenderEvent $event
     */
    public function onMainStylesheet(HookRenderEvent $event)
    {
        $event->add($this->addCSS('assets/less/pop-in.less', [], 'less'));
    }
}
