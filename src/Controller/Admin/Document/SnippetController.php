<?php

declare(strict_types=1);

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 * @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 * @license    http://www.pimcore.org/license GPLv3 and PCL
 */

namespace Pimcore\Bundle\AdminBundle\Controller\Admin\Document;

use Pimcore\Model\Document;
use Pimcore\Model\Element;
use Pimcore\Model\Schedule\Task;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 *
 * @internal
 */
#[Route('/snippet', name: 'pimcore_admin_document_snippet_')]
class SnippetController extends DocumentControllerBase
{

    #[Route('/get-data-by-id', name: 'getdatabyid', methods: [Request::METHOD_GET])]
    public function getDataByIdAction(Request $request): JsonResponse
    {
        if (!$snippet = Document\Snippet::getById((int)$request->get('id'))) {
            throw $this->createNotFoundException('Snippet not found');
        }

        if (($lock = $this->checkForLock($snippet, $request->getSession()->getId())) instanceof JsonResponse) {
            return $lock;
        }

        $snippet = clone $snippet;
        $draftVersion = null;
        $snippet = $this->getLatestVersion($snippet, $draftVersion);

        $versions = Element\Service::getSafeVersionInfo($snippet->getVersions());
        $snippet->setVersions(array_splice($versions, -1, 1));
        $snippet->setParent(null);

        // unset useless data
        $snippet->setEditables(null);

        $data = $snippet->getObjectVars();
        $data['locked'] = $snippet->isLocked();

        $this->addTranslationsData($snippet, $data);
        $this->minimizeProperties($snippet, $data);
        $this->populateUsersNames($snippet, $data);

        $data['url'] = $snippet->getUrl();
        $data['scheduledTasks'] = array_map(
            static function (Task $task) {
                return $task->getObjectVars();
            },
            $snippet->getScheduledTasks()
        );

        if ($snippet->getContentMainDocument()) {
            $data['contentMainDocumentPath'] = $snippet->getContentMainDocument()->getRealFullPath();
        }

        return $this->preSendDataActions($data, $snippet, $draftVersion);
    }

    #[Route('/save', name: 'save', methods: [Request::METHOD_POST, Request::METHOD_PUT])]
    public function saveAction(Request $request): JsonResponse
    {
        $snippet = Document\Snippet::getById((int)$request->get('id'));
        if (!$snippet) {
            throw $this->createNotFoundException('Snippet not found');
        }

        /** @var Document\Snippet|null $snippetSession */
        $snippetSession = $this->getFromSession($snippet, $request->getSession());

        if ($snippetSession) {
            $snippet = $snippetSession;
        } else {
            $snippet = $this->getLatestVersion($snippet);
        }

        if ($request->request->has('missingRequiredEditable')) {
            $snippet->setMissingRequiredEditable($request->request->getBoolean('missingRequiredEditable'));
        }

        [$task, $snippet, $version] = $this->saveDocument($snippet, $request);

        $this->saveToSession($snippet, $request->getSession());

        if ($task == self::TASK_PUBLISH || $task === self::TASK_UNPUBLISH) {

            $treeData = $this->getTreeNodeConfig($snippet);

            return $this->adminJson([
                'success' => true,
                'data' => [
                    'versionDate' => $snippet->getModificationDate(),
                    'versionCount' => $snippet->getVersionCount(),
                ],
                'treeData' => $treeData,
            ]);
        } else {
            $draftData = [];
            if ($version) {
                $draftData = [
                    'id' => $version->getId(),
                    'modificationDate' => $version->getDate(),
                    'isAutoSave' => $version->isAutoSave(),
                ];
            }

            return $this->adminJson([
                'success' => true,
                'draft' => $draftData
            ]);
        }
    }

    protected function setValuesToDocument(Request $request, Document $document): void
    {
        $this->addSettingsToDocument($request, $document);
        $this->addDataToDocument($request, $document);
        $this->applySchedulerDataToElement($request, $document);
        $this->addPropertiesToDocument($request, $document);
    }
}
