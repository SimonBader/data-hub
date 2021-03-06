<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Enterprise License (PEL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license     GPLv3 and PEL
 */

namespace Pimcore\Bundle\DataHubBundle\GraphQL\Resolver;

use GraphQL\Type\Definition\ResolveInfo;
use Pimcore\Bundle\DataHubBundle\Configuration;
use Pimcore\Bundle\DataHubBundle\GraphQL\Exception\ClientSafeException;
use Pimcore\Bundle\DataHubBundle\GraphQL\ElementDescriptor;
use Pimcore\Bundle\DataHubBundle\GraphQL\Helper;
use Pimcore\Bundle\DataHubBundle\GraphQL\Traits\PermissionInfoTrait;
use Pimcore\Bundle\DataHubBundle\GraphQL\Traits\ServiceTrait;
use Pimcore\Bundle\DataHubBundle\Event\GraphQL\ListingEvents;
use Pimcore\Bundle\DataHubBundle\Event\GraphQL\Model\ListingEvent;
use Pimcore\Bundle\DataHubBundle\PimcoreDataHubBundle;
use Pimcore\Bundle\DataHubBundle\WorkspaceHelper;
use Pimcore\Db;
use Pimcore\Model\Asset;
use Pimcore\Model\DataObject\AbstractObject;
use Pimcore\Model\DataObject\ClassDefinition;
use Pimcore\Model\DataObject\Folder;
use Pimcore\Model\DataObject\Listing;
use Pimcore\Model\Document;
use Pimcore\Model\Element\Service;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


class QueryType
{

    use ServiceTrait;
    use PermissionInfoTrait;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var null
     */
    protected $class;

    /**
     * @var null
     */
    protected $configuration;

    /**
     * QueryType constructor.
     * @param EventDispatcherInterface $eventDispatcher
     * @param ClassDefinition $class
     * @param $configuration
     * @param bool $omitPermissionCheck
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, $class = null, $configuration = null, $omitPermissionCheck = false)
    {
        $this->eventDispatcher = $eventDispatcher;
        $this->class = $class;
        $this->configuration = $configuration;
        $this->omitPermissionCheck = $omitPermissionCheck;
    }

    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return array
     * @throws ClientSafeException
     */
    public function resolveFolderGetter($value = null, $args = [], $context, ResolveInfo $resolveInfo = null, $elementType)
    {
        if ($args && isset($args['defaultLanguage'])) {
            $this->getGraphQlService()->getLocaleService()->setLocale($args['defaultLanguage']);
        }

        $element = null;

        if ($elementType == "asset") {
            $element = Asset\Folder::getById($args['id']);
        } else if ($elementType == "document") {
            $element = Document\Folder::getById($args['id']);
        } else if ($elementType == "object") {
            $element = Folder::getById($args['id']);
        }

        if (!$element) {
            return null;
        }

        if (!WorkspaceHelper::isAllowed($element, $context['configuration'], 'read') && !$this->omitPermissionCheck) {
            if (PimcoreDataHubBundle::getNotAllowedPolicy() == PimcoreDataHubBundle::NOT_ALLOWED_POLICY_EXCEPTION) {
                throw new ClientSafeException('not allowed to view element ' . Service::getElementType($element));
            } else {
                return null;
            }
        }

        $data = new ElementDescriptor();
        $getter = "get" . ucfirst($elementType) . "FieldHelper";
        $fieldHelper = $this->getGraphQlService()->$getter();
        $fieldHelper->extractData($data, $element, $args, $context, $resolveInfo);
        $data = $data->getArrayCopy();
        return $data;
    }

    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return array
     * @throws ClientSafeException
     */
    public function resolveAssetFolderGetter($value = null, $args = [], $context, ResolveInfo $resolveInfo = null) {
        return $this->resolveFolderGetter($value, $args, $context, $resolveInfo, "asset");
    }


    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return array
     * @throws ClientSafeException
     */
    public function resolveDocumentFolderGetter($value = null, $args = [], $context, ResolveInfo $resolveInfo = null) {
        return $this->resolveFolderGetter($value, $args, $context, $resolveInfo, "document");
    }

    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return array
     * @throws ClientSafeException
     */
    public function resolveObjectFolderGetter($value = null, $args = [], $context, ResolveInfo $resolveInfo = null) {
        return $this->resolveFolderGetter($value, $args, $context, $resolveInfo, "object");
    }

    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return array
     * @throws ClientSafeException
     */
    public function resolveDocumentGetter($value = null, $args = [], $context, ResolveInfo $resolveInfo = null)
    {
        if ($args && isset($args['defaultLanguage'])) {
            $this->getGraphQlService()->getLocaleService()->setLocale($args['defaultLanguage']);
        }

        $documentElement = null;

        if (isset($args['id'])) {
            $documentElement = Document::getById($args['id']);
        } else if (isset($args['path'])) {
            $documentElement = Document::getByPath($args['path']);
        }

        if (!$documentElement) {
            return null;
        }

        if (!WorkspaceHelper::isAllowed($documentElement, $context['configuration'], 'read') && !$this->omitPermissionCheck ) {
            if (PimcoreDataHubBundle::getNotAllowedPolicy() == PimcoreDataHubBundle::NOT_ALLOWED_POLICY_EXCEPTION) {
                throw new ClientSafeException('not allowed to view document ' . $documentElement->getFullPath());
            } else {
                return null;
            }
        }

        $data = new ElementDescriptor($documentElement);
        $this->getGraphQlService()->extractData($data, $documentElement, $args, $context, $resolveInfo);
        $data = $data->getArrayCopy();

        return $data;
    }


    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return array
     * @throws ClientSafeException
     */
    public function resolveAssetGetter($value = null, $args = [], $context, ResolveInfo $resolveInfo = null)
    {
        if ($args && isset($args['defaultLanguage'])) {
            $this->getGraphQlService()->getLocaleService()->setLocale($args['defaultLanguage']);
        }

        $assetElement = Asset::getById($args['id']);
        if (!$assetElement) {
            return null;
        }

        if (!WorkspaceHelper::isAllowed($assetElement, $context['configuration'], 'read') && !$this->omitPermissionCheck ) {
            if (PimcoreDataHubBundle::getNotAllowedPolicy() == PimcoreDataHubBundle::NOT_ALLOWED_POLICY_EXCEPTION) {
                throw new ClientSafeException('not allowed to view asset ' . $assetElement->getFullPath());
            } else {
                return null;
            }
        }

        $data = new ElementDescriptor($assetElement);
        $this->getGraphQlService()->extractData($data, $assetElement, $args, $context, $resolveInfo);
        return $data;
    }


    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return array
     * @throws ClientSafeException
     */
    public function resolveObjectGetter($value = null, $args = [], $context, ResolveInfo $resolveInfo = null)
    {

        if (!$args["id"]) {
            return null;
        }

        if ($args && isset($args['defaultLanguage'])) {
            $this->getGraphQlService()->getLocaleService()->setLocale($args['defaultLanguage']);
        }

        $modelFactory = $this->getGraphQlService()->getModelFactory();
        $listClass = 'Pimcore\\Model\\DataObject\\' . ucfirst($this->class->getName()) . '\\Listing';
        /** @var Listing $objectList */
        $objectList = $modelFactory->build($listClass);
        $conditionParts = [];

        $conditionParts[] = '(o_id =' . $args['id'] . ')';

        /** @var $configuration Configuration */
        $configuration = $context['configuration'];
        $sqlGetCondition = $configuration->getSqlObjectCondition();

        if ($sqlGetCondition) {
            $conditionParts[] = '(' . $sqlGetCondition . ')';
        }

        if ($conditionParts) {
            $condition = implode(' AND ', $conditionParts);
            $objectList->setCondition($condition);
        }

        $objectList->setObjectTypes([AbstractObject::OBJECT_TYPE_OBJECT, AbstractObject::OBJECT_TYPE_FOLDER, AbstractObject::OBJECT_TYPE_VARIANT]);
        $objectList->setLimit(1);
        $objectList->setUnpublished(1);
        $objectList = $objectList->load();
        if (!$objectList) {
            throw new ClientSafeException('object with ID ' . $args["id"] . ' not found');
        }
        $object = $objectList[0];

        if (!WorkspaceHelper::isAllowed($object, $configuration, 'read') && !$this->omitPermissionCheck) {
            throw new ClientSafeException('permission denied. check your workspace settings');
        }

        $data = new ElementDescriptor($object);
        $data['id'] = $object->getId();
        $this->getGraphQlService()->extractData($data, $object, $args, $context, $resolveInfo);

        return $data;
    }

    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return mixed
     */
    public function resolveEdge($value = null, $args = [], $context, ResolveInfo $resolveInfo = null)
    {
        $nodeData = $value['node'];
        $objectId = $nodeData['id'];

        $object = AbstractObject::getById($objectId);

        $data = new ElementDescriptor();
        if (WorkspaceHelper::isAllowed($object, $this->configuration, 'read') && !$this->omitPermissionCheck) {
            $fieldHelper = $this->getGraphQlService()->getObjectFieldHelper();
            $nodeData = $fieldHelper->extractData($data, $object, $args, $context, $resolveInfo);
        }

        return $nodeData;
    }


    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return mixed
     */
    public function resolveEdges($value = null, $args = [], $context, ResolveInfo $resolveInfo = null)
    {
        return $value['edges'];
    }

    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return array
     * @throws \Exception
     */
    public function resolveListing($value = null, $args = [], $context, ResolveInfo $resolveInfo = null)
    {
        if ($args && isset($args['defaultLanguage'])) {
            $this->getGraphQlService()->getLocaleService()->setLocale($args['defaultLanguage']);
        }

        $modelFactory = $this->getGraphQlService()->getModelFactory();
        $listClass = 'Pimcore\\Model\\DataObject\\' . ucfirst($this->class->getName()) . '\\Listing';
        /** @var Listing $objectList */
        $objectList = $modelFactory->build($listClass);
        $conditionParts = [];
        if (isset($args['ids'])) {
            $conditionParts[] = '(o_id IN (' . $args['ids'] . '))';
        }

        // paging
        if (isset($args['first'])) {
            $objectList->setLimit($args['first']);
        }

        if (isset($args['after'])) {
            $objectList->setOffset($args['after']);
        }

        // sorting
        if (!empty($args['sortBy'])) {
            $objectList->setOrderKey($args['sortBy']);
            if (!empty($args['sortOrder'])) {
                $objectList->setOrder($args['sortOrder']);
            }
        }

        // Include unpublished
        if (isset($args['published']) && $args['published'] === false) {
            $objectList->setUnpublished(true);
        }

        /** @var $configuration Configuration */
        $configuration = $context['configuration'];
        $sqlListCondition = $configuration->getSqlObjectCondition();

        if ($sqlListCondition) {
            $conditionParts[] = '(' . $sqlListCondition . ')';
        }

        // check permissions
        $db = Db::get();
        $workspacesTableName = 'plugin_datahub_workspaces_object';
        $conditionParts[] = ' (
            (
                SELECT `read` from ' . $db->quoteIdentifier($workspacesTableName) . '
                WHERE ' . $db->quoteIdentifier($workspacesTableName) . '.configuration = ' . $db->quote($configuration->getName()) . '
                AND LOCATE(CONCAT(' . $db->quoteIdentifier($objectList->getTableName()) . '.o_path,' . $db->quoteIdentifier($objectList->getTableName()) . '.o_key),' . $db->quoteIdentifier($workspacesTableName) . '.cpath)=1
                ORDER BY LENGTH(' . $db->quoteIdentifier($workspacesTableName) . '.cpath) DESC
                LIMIT 1
            )=1
            OR
            (
                SELECT `read` from ' . $db->quoteIdentifier($workspacesTableName) . '
                WHERE ' . $db->quoteIdentifier($workspacesTableName) . '.configuration = ' . $db->quote($configuration->getName()) . '
                AND LOCATE(' . $db->quoteIdentifier($workspacesTableName) . '.cpath,CONCAT(' . $db->quoteIdentifier($objectList->getTableName()) . '.o_path,' . $db->quoteIdentifier($objectList->getTableName()) . '.o_key))=1
                ORDER BY LENGTH(' . $db->quoteIdentifier($workspacesTableName) . '.cpath) DESC
                LIMIT 1
            )=1
        )';

        if (isset($args['filter'])) {
            $filter = json_decode($args['filter'], false);
            if (!$filter) {
                throw new ClientSafeException('unable to decode filter');
            }
            $filterCondition = Helper::buildSqlCondition($objectList->getTableName(), $filter);
            $conditionParts[] = $filterCondition;
        }

        if ($conditionParts) {
            $condition = implode(' AND ', $conditionParts);
            $objectList->setCondition($condition);
        }

        $objectList->setObjectTypes([AbstractObject::OBJECT_TYPE_OBJECT, AbstractObject::OBJECT_TYPE_FOLDER, AbstractObject::OBJECT_TYPE_VARIANT]);

        $event =  new ListingEvent(
            $objectList,
            $args,
            $context,
            $resolveInfo
        );
        $this->eventDispatcher->dispatch(ListingEvents::PRE_LOAD, $event);
        $objectList = $event->getListing();

        $totalCount = $objectList->getTotalCount();
        $objectList = $objectList->load();

        $nodes = [];

        foreach ($objectList as $object) {
            $data = [];
            $data['id'] = $object->getId();
            $nodes[] = [
                'cursor' => 'object-' . $object->getId(),
                'node' => $data,
            ];
        }
        $connection = [];
        $connection['edges'] = $nodes;
        $connection['totalCount'] = $totalCount;

        return $connection;
    }


    /**
     * @param null $value
     * @param array $args
     * @param array $context
     * @param ResolveInfo|null $resolveInfo
     * @return mixed
     */
    public function resolveListingTotalCount($value = null, $args = [], $context, ResolveInfo $resolveInfo = null)
    {
        return $value['totalCount'];
    }

}
