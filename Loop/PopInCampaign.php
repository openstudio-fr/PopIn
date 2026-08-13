<?php

namespace PopIn\Loop;

use PopIn\Model\Map\PopInCampaignTableMap;
use PopIn\Model\PopInCampaignQuery;
use Propel\Runtime\ActiveQuery\Criteria;
use Thelia\Core\Template\Element\BaseI18nLoop;
use Thelia\Core\Template\Element\BaseLoop;
use Thelia\Core\Template\Element\LoopResult;
use Thelia\Core\Template\Element\LoopResultRow;
use Thelia\Core\Template\Element\PropelSearchLoopInterface;
use Thelia\Core\Template\Loop\Argument\Argument;
use Thelia\Core\Template\Loop\Argument\ArgumentCollection;

/**
 * Pop-in campaigns loop.
 */
class PopInCampaign extends BaseI18nLoop implements PropelSearchLoopInterface
{

    /**
     * @param LoopResult $loopResult
     *
     * @return LoopResult
     */
    public function parseResults(LoopResult $loopResult): LoopResult
    {
        /** @var \PopIn\Model\PopInCampaign $entry */
        foreach ($loopResult->getResultDataCollection() as $entry) {
            $row = new LoopResultRow($entry);

            $row
                ->set("ID", $entry->getId())
                ->set("START", $entry->getStart())
                ->set("END", $entry->getEnd())
                ->set("CONTENT_SOURCE_TYPE", $entry->getContentSourceType())
                ->set("CONTENT_SOURCE_ID", $entry->getContentSourceId())
                ->set("CUSTOM_TITLE", $entry->getVirtualColumn("i18n_CUSTOM_TITLE"))
                ->set("CUSTOM_DESCRIPTION", $entry->getVirtualColumn("i18n_CUSTOM_DESCRIPTION"))
                ->set("CUSTOM_POSTSCRIPTUM", $entry->getVirtualColumn("i18n_CUSTOM_POSTSCRIPTUM"))
                ->set("CUSTOM_LINK",$entry->getVirtualColumn("i18n_CUSTOM_LINK"))
                ->set("CUSTOM_LINK_TEXT",$entry->getVirtualColumn("i18n_CUSTOM_LINK_TEXT"))
                ->set("EXCLUDE_CATEGORY_IDS",$entry->getExcludeCategoryIds())
                ->set("EXCLUDE_FOLDER_IDS",$entry->getExcludeFolderIds())
                ->set("EXCLUDE_CONTENT_IDS",$entry->getExcludeContentIds())
                ->set("EXCLUDE_HOME",$entry->getExcludeHome())
                ->set("EXCLUDE_URL",$entry->getExcludeUrl())
                ->set("PERSISTENT",$entry->getPersistent())
            ;

            $loopResult->addRow($row);
        }

        return $loopResult;
    }

    /**
     * Definition of loop arguments
     *
     * example :
     *
     * public function getArgDefinitions()
     * {
     *  return new ArgumentCollection(
     *
     *       Argument::createIntListTypeArgument('id'),
     *           new Argument(
     *           'ref',
     *           new TypeCollection(
     *               new Type\AlphaNumStringListType()
     *           )
     *       ),
     *       Argument::createIntListTypeArgument('category'),
     *       Argument::createBooleanTypeArgument('new'),
     *       ...
     *   );
     * }
     *
     * @return \Thelia\Core\Template\Loop\Argument\ArgumentCollection
     */
    protected function getArgDefinitions(): \Thelia\Core\Template\Loop\Argument\ArgumentCollection
    {
        return new ArgumentCollection(
            Argument::createIntListTypeArgument("id"),
            // Restaure du T2 (override/modules/PopIn) : sans cette declaration
            // BaseLoop::initializeArgs() ignore SILENCIEUSEMENT `check_date=1`
            // dans les templates, et la loop renvoie aussi les campagnes expirees.
            Argument::createBooleanTypeArgument("check_date", false),
            Argument::createAnyTypeArgument("content_source_type"),
            Argument::createAnyTypeArgument("exclude_content_source_type"),
            Argument::createAnyTypeArgument("content_source_id"),
            Argument::createEnumListTypeArgument(
                "order",
                [
                    "id",
                    "id-reverse",
                    "start",
                    "start-reverse",
                    "end",
                    "end-reverse",
                    "content_source_type",
                    "content_source_type-reverse",
                    "content_source_id",
                    "content_source_id-reverse",
                ],
                "id"
            )
        );
    }

    /**
     * this method returns a Propel ModelCriteria
     *
     * @return \Propel\Runtime\ActiveQuery\ModelCriteria
     */
    public function buildModelCriteria(): \Propel\Runtime\ActiveQuery\ModelCriteria
    {
        $query = PopInCampaignQuery::create();

        if (true === $this->getCheckDate()) {
            $now = new \DateTime();

            // Fenetre de validite : (start IS NULL OR start <= now)
            //                   AND (end   IS NULL OR end   >= now)
            // L'enchainement `_or()` / `_and()` du T2 basculait tout le WHERE en OR
            // et donnait `A OR (B AND C) OR D`. On groupe explicitement.
            $query
                ->condition('start_null', PopInCampaignTableMap::COL_START.Criteria::ISNULL)
                ->condition('start_past', PopInCampaignTableMap::COL_START.Criteria::LESS_EQUAL.'?', $now)
                ->combine(['start_null', 'start_past'], Criteria::LOGICAL_OR, 'started')
                ->condition('end_null', PopInCampaignTableMap::COL_END.Criteria::ISNULL)
                ->condition('end_future', PopInCampaignTableMap::COL_END.Criteria::GREATER_EQUAL.'?', $now)
                ->combine(['end_null', 'end_future'], Criteria::LOGICAL_OR, 'not_ended')
                // combine() sans nom applique directement la condition au WHERE.
                ->combine(['started', 'not_ended'], Criteria::LOGICAL_AND);
        }

        if (null !== $id = $this->getId()) {
            $query->filterById($id);
        }

        if (null !== $exclude_content_source_type = $this->getExcludeContentSourceType()) {
            $exclude_content_source_type = array_map("trim", explode(",", $exclude_content_source_type));
            $query->filterByContentSourceType($exclude_content_source_type, Criteria::NOT_IN);
        }

        if (null !== $content_source_type = $this->getContentSourceType()) {
            $content_source_type = array_map("trim", explode(",", $content_source_type));
            $query->filterByContentSourceType($content_source_type);
        }

        if (null !== $content_source_id = $this->getContentSourceId()) {
            $content_source_id = array_map("trim", explode(",", $content_source_id));
            $query->filterByContentSourceId($content_source_id);
        }

        foreach ($this->getOrder() as $order) {
            switch ($order) {
                case "id":
                    $query->orderById();
                    break;
                case "id-reverse":
                    $query->orderById(Criteria::DESC);
                    break;
                case "start":
                    $query->orderByStart();
                    break;
                case "start-reverse":
                    $query->orderByStart(Criteria::DESC);
                    break;
                case "end":
                    $query->orderByEnd();
                    break;
                case "end-reverse":
                    $query->orderByEnd(Criteria::DESC);
                    break;
                case "content_source_type":
                    $query->orderByContentSourceType();
                    break;
                case "content_source_type-reverse":
                    $query->orderByContentSourceType(Criteria::DESC);
                    break;
                case "content_source_id":
                    $query->orderByContentSourceId();
                    break;
                case "content_source_id-reverse":
                    $query->orderByContentSourceId(Criteria::DESC);
                    break;
            }
        }

        $this->configureI18nProcessing(
            $query,
            [
                'CUSTOM_TITLE',
                'CUSTOM_DESCRIPTION',
                'CUSTOM_POSTSCRIPTUM',
                'CUSTOM_LINK',
                'CUSTOM_LINK_TEXT'
            ],
            null,
            'ID',
            true
        );

        return $query;
    }
}
