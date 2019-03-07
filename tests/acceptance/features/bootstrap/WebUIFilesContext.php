<?php
/**
 * ownCloud
 *
 * @author Artur Neumann <artur@jankaritech.com>
 * @copyright Copyright (c) 2017 Artur Neumann artur@jankaritech.com
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License,
 * as published by the Free Software Foundation;
 * either version 3 of the License, or any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 *
 */

use Behat\Behat\Context\Context;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Behat\MinkExtension\Context\RawMinkContext;
use Page\FavoritesPage;
use Page\FilesPage;
use Page\OwncloudPage;
use Page\SharedByLinkPage;
use Page\SharedWithOthersPage;
use Page\SharedWithYouPage;
use Page\TagsPage;
use Page\TrashbinPage;
use Page\FilesPageElement\ConflictDialog;
use Page\FilesPageElement\FileActionsMenu;
use SensioLabs\Behat\PageObjectExtension\PageObject\Exception\ElementNotFoundException;
use TestHelpers\DeleteHelper;
use TestHelpers\Asserts\WebDav as WebDavAssert;

require_once 'bootstrap.php';

/**
 * WebUI Files context.
 */
class WebUIFilesContext extends RawMinkContext implements Context {

	/**
	 *
	 * @var FilesPage
	 */
	private $filesPage;
	
	/**
	 *
	 * @var TrashbinPage
	 */
	private $trashbinPage;
	
	/**
	 *
	 * @var FavoritesPage
	 */
	private $favoritesPage;
	
	/**
	 *
	 * @var SharedWithYouPage
	 */
	private $sharedWithYouPage;

	/**
	 *
	 * @var SharedByLinkPage
	 */
	private $sharedByLinkPage;

	/**
	 * @var SharedWithOthersPage
	 */
	private $sharedWithOthersPage;

	/**
	 *
	 * @var TagsPage
	 */
	private $tagsPage;

	/**
	 *
	 * @var ConflictDialog
	 */
	private $conflictDialog;

	/**
	 *
	 * @var FileActionsMenu
	 */
	private $openedFileActionMenu;

	/**
	 * Table of all files and folders that should have been deleted, stored so
	 * that other steps can use the list to check if the deletion happened correctly
	 * table headings: must be: |name|
	 *
	 * @var TableNode
	 */
	private $deletedElementsTable = null;

	/**
	 * Table of all files and folders that should have been moved, stored so
	 * that other steps can use the list to check if the moving happened correctly
	 * table headings: must be: |name|
	 *
	 * @var TableNode
	 */
	private $movedElementsTable = null;

	/**
	 * variable to remember in which folder we are currently working
	 *
	 * @var string
	 */
	private $currentFolder = "";

	/**
	 * variable to remember with which file we are currently working
	 *
	 * @var string
	 */
	private $currentFile = "";

	/**
	 *
	 * @var FeatureContext
	 */
	private $featureContext;

	/**
	 *
	 * @var WebUIGeneralContext
	 */
	private $webUIGeneralContext;

	private $uploadConflictDialogTitle = "file conflict";

	/**
	 * WebUIFilesContext constructor.
	 *
	 * @param FilesPage $filesPage
	 * @param TrashbinPage $trashbinPage
	 * @param ConflictDialog $conflictDialog
	 * @param FavoritesPage $favoritesPage
	 * @param SharedWithYouPage $sharedWithYouPage
	 * @param TagsPage $tagsPage
	 * @param SharedByLinkPage $sharedByLinkPage
	 * @param SharedWithOthersPage $sharedWithOthersPage
	 *
	 * @return void
	 */
	public function __construct(
		FilesPage $filesPage,
		TrashbinPage $trashbinPage,
		ConflictDialog $conflictDialog,
		FavoritesPage $favoritesPage,
		SharedWithYouPage $sharedWithYouPage,
		TagsPage $tagsPage,
		SharedByLinkPage $sharedByLinkPage,
		SharedWithOthersPage $sharedWithOthersPage
	) {
		$this->trashbinPage = $trashbinPage;
		$this->filesPage = $filesPage;
		$this->conflictDialog = $conflictDialog;
		$this->favoritesPage = $favoritesPage;
		$this->sharedWithYouPage = $sharedWithYouPage;
		$this->tagsPage = $tagsPage;
		$this->sharedByLinkPage = $sharedByLinkPage;
		$this->sharedWithOthersPage = $sharedWithOthersPage;
	}

	/**
	 * returns the set page object from WebUIGeneralContext::getCurrentPageObject()
	 * or if that is null the files page object
	 *
	 * @return OwncloudPage
	 */
	private function getCurrentPageObject() {
		$pageObject = $this->webUIGeneralContext->getCurrentPageObject();
		if ($pageObject === null) {
			$pageObject = $this->filesPage;
		}
		return $pageObject;
	}

	/**
	 * get the current folder and file path that is being worked on
	 *
	 * @return string
	 */
	private function getCurrentFolderFilePath() {
		return \rtrim($this->currentFolder, '/') . '/' . $this->currentFile;
	}

	/**
	 * reset any context remembered about where we are or what we have done on
	 * the files-like pages
	 *
	 * @return void
	 */
	public function resetFilesContext() {
		$this->currentFolder = "";
		$this->currentFile = "";
		$this->deletedElementsTable = null;
		$this->movedElementsTable = null;
	}

	/**
	 * @When the user browses to the files page
	 * @Given the user has browsed to the files page
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserBrowsesToTheFilesPage() {
		$this->filesPage->setPagePath(
			$this->webUIGeneralContext->getCurrentServer() .
			$this->filesPage->getOriginalPath()
		);
		if (!$this->filesPage->isOpen()) {
			$this->filesPage->open();
			$this->filesPage->waitTillPageIsLoaded($this->getSession());
			$this->webUIGeneralContext->setCurrentPageObject($this->filesPage);
		}
	}

	/**
	 * @When the user browses directly to display the :tabName details of file :fileName in folder :folderName
	 * @Given the user has browsed directly to display the :tabName details of file :fileName in folder :folderName
	 *
	 * @param string $tabName
	 * @param string $fileName
	 * @param string $folderName
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theUserBrowsesDirectlyToDetailsTabOfFileInFolder(
		$tabName, $fileName, $folderName
	) {
		$this->currentFolder = '/' . \trim($folderName, '/');
		$this->currentFile = $fileName;
		$fileId = $this->featureContext->getFileIdForPath(
			$this->featureContext->getCurrentUser(),
			$this->getCurrentFolderFilePath()
		);
		$this->filesPage->browseToFileId(
			$fileId, $this->currentFolder, $tabName
		);
		$this->filesPage->waitTillPageIsLoaded($this->getSession());
		$this->filesPage->getDetailsDialog()->waitTillPageIsLoaded($this->getSession());
	}

	/**
	 * @Given the user has browsed directly to display the details of file :fileName in folder :folderName
	 * @When the user browses directly to display the details of file :fileName in folder :folderName
	 *
	 * @param string $fileName
	 * @param string $folderName
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theUserBrowsesDirectlyToDetailsDefaultTabOfFileInFolder($fileName, $folderName) {
		$this->theUserBrowsesDirectlyToDetailsTabOfFileInFolder(null, $fileName, $folderName);
	}

	/**
	 * @Then the thumbnail should be visible in the details panel
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theThumbnailShouldBeVisibleInTheDetailsPanel() {
		$detailsDialog = $this->filesPage->getDetailsDialog();
		$thumbnail = $detailsDialog->findThumbnail();
		PHPUnit_Framework_Assert::assertTrue(
			$thumbnail->isVisible(),
			"thumbnail is not visible"
		);
		$style = $thumbnail->getAttribute("style");
		PHPUnit_Framework_Assert::assertNotNull(
			$style,
			'style attribute of details thumbnail is null'
		);
		PHPUnit_Framework_Assert::assertContains(
			$this->getCurrentFolderFilePath(),
			$style
		);
	}

	/**
	 * @Then the :tabName details panel should be visible
	 *
	 * @param string $tabName
	 *
	 * @return void
	 */
	public function theTabNameDetailsPanelShouldBeVisible($tabName) {
		$detailsDialog = $this->filesPage->getDetailsDialog();
		PHPUnit_Framework_Assert::assertTrue(
			$detailsDialog->isDetailsPanelVisible($tabName),
			"the $tabName panel is not visible in the details panel"
		);
	}

	/**
	 * @Then the share-with field should be visible in the details panel
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theShareWithFieldShouldBeVisibleInTheDetailsPanel() {
		$sharingDialog = $this->filesPage->getSharingDialog();
		PHPUnit_Framework_Assert::assertTrue(
			$sharingDialog->isShareWithFieldVisible(),
			'the share-with field is not visible in the details panel'
		);
	}

	/**
	 * @Then the share-with field should not be visible in the details panel
	 *
	 * @return void
	 * @throws Exception
	 */
	public function theShareWithFieldShouldNotBeVisibleInTheDetailsPanel() {
		$sharingDialog = $this->filesPage->getSharingDialog();
		PHPUnit_Framework_Assert::assertFalse(
			$sharingDialog->isShareWithFieldVisible(),
			'the share-with field is visible in the details panel'
		);
	}

	/**
	 * @When the user browses to the trashbin page
	 * @Given the user has browsed to the trashbin page
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserBrowsesToTheTrashbinPage() {
		$this->trashbinPage->setPagePath(
			$this->webUIGeneralContext->getCurrentServer() .
			$this->trashbinPage->getOriginalPath()
		);
		if (!$this->trashbinPage->isOpen()) {
			$this->trashbinPage->open();
			$this->trashbinPage->waitTillPageIsLoaded($this->getSession());
			$this->webUIGeneralContext->setCurrentPageObject($this->trashbinPage);
		}
	}

	/**
	 * @When the user browses to the favorites page
	 * @Given the user has browsed to the favorites page
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserBrowsesToTheFavoritesPage() {
		$this->favoritesPage->setPagePath(
			$this->webUIGeneralContext->getCurrentServer() .
			$this->favoritesPage->getOriginalPath()
		);
		if (!$this->favoritesPage->isOpen()) {
			$this->favoritesPage->open();
			$this->favoritesPage->waitTillPageIsLoaded($this->getSession());
			$this->webUIGeneralContext->setCurrentPageObject($this->favoritesPage);
		}
	}

	/**
	 * @When the user browses to the shared-with-you page
	 * @Given the user has browsed to the shared-with-you page
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserBrowsesToTheSharedWithYouPage() {
		$this->sharedWithYouPage->setPagePath(
			$this->webUIGeneralContext->getCurrentServer() .
			$this->sharedWithYouPage->getOriginalPath()
		);
		if (!$this->sharedWithYouPage->isOpen()) {
			$this->sharedWithYouPage->open();
			$this->sharedWithYouPage->waitTillPageIsLoaded($this->getSession());
			$this->webUIGeneralContext->setCurrentPageObject(
				$this->sharedWithYouPage
			);
		}
	}

	/**
	 * @When the user browses to the shared-by-link page
	 * @Given the user has browsed to the shared-by-link page
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserBrowsesToTheSharedByLinkPage() {
		$this->sharedByLinkPage->setPagePath(
			$this->webUIGeneralContext->getCurrentServer() .
			$this->sharedByLinkPage->getOriginalPath()
		);
		if (!$this->sharedByLinkPage->isOpen()) {
			$this->sharedByLinkPage->open();
			$this->sharedByLinkPage->waitTillPageIsLoaded($this->getSession());
			$this->webUIGeneralContext->setCurrentPageObject(
				$this->sharedByLinkPage
			);
		}
	}

	/**
	 * @When the user browses to the shared-with-others page
	 * @Given the user has browsed to the shared-with-others page
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserBrowsesToTheSharedWithOthersPage() {
		$this->sharedWithOthersPage->setPagePath(
			$this->webUIGeneralContext->getCurrentServer() .
			$this->sharedWithOthersPage->getOriginalPath()
		);
		if (!$this->sharedWithOthersPage->isOpen()) {
			$this->sharedWithOthersPage->open();
			$this->sharedWithOthersPage->waitTillPageIsLoaded($this->getSession());
			$this->webUIGeneralContext->setCurrentPageObject(
				$this->sharedWithOthersPage
			);
		}
	}

	/**
	 * @When the user browses to the tags page
	 * @Given the user has browsed to the tags page
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserBrowsesToTheTagsPage() {
		$this->tagsPage->setPagePath(
			$this->webUIGeneralContext->getCurrentServer() .
			$this->tagsPage->getOriginalPath()
		);
		if (!$this->tagsPage->isOpen()) {
			$this->tagsPage->open();
			$this->tagsPage->waitTillPageIsLoaded($this->getSession());
			$this->webUIGeneralContext->setCurrentPageObject(
				$this->tagsPage
			);
		}
	}

	/**
	 * @When /^the user creates a folder with the (invalid|)\s?name ((?:'[^']*')|(?:"[^"]*")) using the webUI$/
	 * @Given /^the user has created a folder with the (invalid|)\s?name ((?:'[^']*')|(?:"[^"]*")) using the webUI$/
	 *
	 * @param string $invalid contains "invalid"
	 *                        if the folder creation is expected to fail
	 * @param string $name enclosed in single or double quotes
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserCreatesAFolderUsingTheWebUI($invalid, $name) {
		// The capturing group of the regex always includes the quotes at each
		// end of the captured string, so trim them.
		$name = \trim($name, $name[0]);
		try {
			$this->createAFolder($name);
			if ($invalid === "invalid") {
				throw new Exception(
					"folder '$name' should not have been created but was"
				);
			}
		} catch (Exception $e) {
			//do not throw the exception if we expect the folder creation to fail
			if ($invalid !== "invalid"
				|| $e->getMessage() !== "could not create folder"
			) {
				throw $e;
			}
		}
	}

	/**
	 * @param string $name
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function createAFolder($name) {
		$session = $this->getSession();
		$pageObject = $this->getCurrentPageObject();
		$pageObject->createFolder($session, $name);
		$pageObject->waitTillPageIsLoaded($session);
	}

	/**
	 * @When the user creates a folder with the following name using the webUI
	 * @Given the user has created a folder with the following name using the webUI
	 *
	 * @param TableNode $namePartsTable table of parts of the file name
	 *                                  table headings: must be: |name-parts |
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserCreatesTheFollowingFolderUsingTheWebUI(
		TableNode $namePartsTable
	) {
		$fileName = '';

		foreach ($namePartsTable as $namePartsRow) {
			$fileName .= $namePartsRow['name-parts'];
		}

		$this->createAFolder($fileName);
	}

	/**
	 * @Then there should be no files\/folders listed on the webUI
	 *
	 * @return void
	 */
	public function thereShouldBeNoFilesFoldersListedOnTheWebUI() {
		$pageObject = $this->getCurrentPageObject();
		PHPUnit_Framework_Assert::assertEquals(
			0,
			$pageObject->getSizeOfFileFolderList()
		);
	}

	/**
	 * @Then there should be exactly :count files\/folders listed on the webUI
	 * @Then there should be exactly :count file/files listed on the webUI
	 * @Then there should be exactly :count folder/folders listed on the webUI
	 *
	 * @param string $count that is numeric
	 *
	 * @return void
	 */
	public function thereShouldBeCountFilesFoldersListedOnTheWebUI($count) {
		$pageObject = $this->getCurrentPageObject();
		PHPUnit_Framework_Assert::assertEquals(
			$count,
			$pageObject->getSizeOfFileFolderList()
		);
	}

	/**
	 * @When the user creates so many files\/folders that they do not fit in one browser page
	 * @Given so many files\/folders have been created that they do not fit in one browser page
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theListOfFilesFoldersDoesNotFitInOneBrowserPage() {
		$windowHeight = $this->filesPage->getWindowHeight(
			$this->getSession()
		);
		$itemsCount = $this->filesPage->getSizeOfFileFolderList();
		$lastItemCoordinates['top'] = 0;
		if ($itemsCount > 0) {
			$lastItemCoordinates = $this->filesPage->getCoordinatesOfElement(
				$this->getSession(),
				$this->filesPage->findFileActionsMenuBtnByNo($itemsCount)
			);
		}

		while ($windowHeight > $lastItemCoordinates['top']) {
			$this->filesPage->createFolder($this->getSession());
			$itemsCount = $this->filesPage->getSizeOfFileFolderList();
			$lastItemCoordinates = $this->filesPage->getCoordinatesOfElement(
				$this->getSession(),
				$this->filesPage->findFileActionsMenuBtnByNo($itemsCount)
			);
		}
		$this->webUIGeneralContext->theUserReloadsTheCurrentPageOfTheWebUI();
	}

	/**
	 * @When the user renames file/folder :fromName to :toName using the webUI
	 * @Given the user has renamed file/folder :fromName to :toName using the webUI
	 *
	 * @param string $fromName
	 * @param string $toName
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserRenamesFileFolderToUsingTheWebUI(
		$fromName, $toName
	) {
		$pageObject = $this->getCurrentPageObject();
		$pageObject->waitTillPageIsLoaded($this->getSession());
		$pageObject->renameFile($fromName, $toName, $this->getSession());
	}

	/**
	 * @When the user renames the following file/folder using the webUI
	 * @Given the user has renamed the following file/folder using the webUI
	 *
	 * @param TableNode $namePartsTable table of parts of the from and to file names
	 *                                  table headings: must be:
	 *                                  |from-name-parts |to-name-parts |
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserRenamesTheFollowingFileFolderToUsingTheWebUI(
		TableNode $namePartsTable
	) {
		$fromNameParts = [];
		$toNameParts = [];

		foreach ($namePartsTable as $namePartsRow) {
			$fromNameParts[] = $namePartsRow['from-name-parts'];
			$toNameParts[] = $namePartsRow['to-name-parts'];
		}
		$pageObject = $this->getCurrentPageObject();
		$pageObject->waitTillPageIsLoaded($this->getSession());
		$pageObject->renameFile(
			$fromNameParts,
			$toNameParts,
			$this->getSession()
		);
	}

	/**
	 * @When the user renames file/folder :fromName to one of these names using the webUI
	 * @Given the user has renamed file/folder :fromName to one of these names using the webUI
	 *
	 * @param string $fromName
	 * @param TableNode $table
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserRenamesFileToOneOfTheseNamesUsingTheWebUI(
		$fromName, TableNode $table
	) {
		$pageObject = $this->getCurrentPageObject();
		$pageObject->waitTillPageIsLoaded($this->getSession());
		foreach ($table->getRows() as $row) {
			$pageObject->renameFile($fromName, $row[0], $this->getSession());
		}
	}

	/**
	 * Delete a file on the current page. The current page should be one that
	 * has rows of files.
	 *
	 * @param string $name
	 * @param bool $expectToDeleteFile if true, then the caller expects that the file can be deleted
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function deleteTheFileUsingTheWebUI($name, $expectToDeleteFile = true) {
		$pageObject = $this->getCurrentPageObject();
		$session = $this->getSession();
		$pageObject->waitTillPageIsLoaded($session);
		if ($expectToDeleteFile) {
			$pageObject->deleteFile($name, $session, $expectToDeleteFile);
		} else {
			// We do not expect to be able to delete the file,
			// so do not waste time doing too many retries.
			$pageObject->deleteFile(
				$name, $session, $expectToDeleteFile, MINIMUM_RETRY_COUNT
			);
		}
	}

	/**
	 * for a folder or individual file that is shared, the receiver of the share
	 * has an "Unshare" entry in the file actions menu. Clicking it works just
	 * like delete.
	 *
	 * @When the user deletes/unshares file/folder :name using the webUI
	 * @Given the user has deleted/unshared file/folder :name using the webUI
	 *
	 * @param string $name
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserDeletesFileUsingTheWebUI($name) {
		$this->deleteTheFileUsingTheWebUI($name);
	}

	/**
	 * @When the user deletes the following file/folder using the webUI
	 * @Given the user has deleted the following file/folder using the webUI
	 *
	 * @param TableNode $namePartsTable table of parts of the file name
	 *                                  table headings: must be: |name-parts |
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserDeletesTheFollowingFileUsingTheWebUI(
		TableNode $namePartsTable
	) {
		$fileNameParts = [];

		foreach ($namePartsTable as $namePartsRow) {
			$fileNameParts[] = $namePartsRow['name-parts'];
		}
		$pageObject = $this->getCurrentPageObject();
		$pageObject->waitTillPageIsLoaded($this->getSession());
		$pageObject->deleteFile($fileNameParts, $this->getSession());
	}

	/**
	 * @Given the following files/folders have been deleted
	 *
	 * @param TableNode $filesTable table headings: must be: |name|
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theFollowingFilesFoldersHaveBeenDeleted(TableNode $filesTable) {
		foreach ($filesTable as $file) {
			$username = $this->featureContext->getCurrentUser();
			$currentTime = \microtime(true);
			$end = $currentTime + (LONG_UI_WAIT_TIMEOUT_MILLISEC / 1000);
			//retry deleting in case the file is locked (code 403)
			while ($currentTime <= $end) {
				$response = DeleteHelper::delete(
					$this->featureContext->getBaseUrl(),
					$username,
					$this->featureContext->getUserPassword($username),
					$file['name']
				);
				
				if ($response->getStatusCode() >= 200
					&& $response->getStatusCode() <= 399
				) {
					break;
				} elseif ($response->getStatusCode() === 423) {
					$message = "INFORMATION: file '" . $file['name'] .
					"' is locked";
					\error_log($message);
				} else {
					throw new \Exception(
						"could not delete file. Response code: " .
						$response->getStatusCode()
					);
				}
				
				\usleep(STANDARD_SLEEP_TIME_MICROSEC);
				$currentTime = \microtime(true);
			}
			
			if ($currentTime > $end) {
				throw new \Exception(
					__METHOD__ . " timeout deleting files by WebDAV"
				);
			}
		}
	}

	/**
	 * @When the user deletes the following elements using the webUI
	 * @Given the user has deleted the following elements using the webUI
	 *
	 * @param TableNode $table table of file names
	 *                         table headings: must be: |name|
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserDeletesTheFollowingElementsUsingTheWebUI(
		TableNode $table
	) {
		$this->deletedElementsTable = $table;
		foreach ($this->deletedElementsTable as $file) {
			$this->deleteTheFileUsingTheWebUI($file['name']);
		}
	}

	/**
	 * @When the user moves file/folder :name into folder :destination using the webUI
	 * @Given the user has moved file/folder :name into folder :destination using the webUI
	 *
	 * @param string|array $name
	 * @param string|array $destination
	 *
	 * @return void
	 */
	public function theUserMovesFileFolderIntoFolderUsingTheWebUI($name, $destination) {
		$pageObject = $this->getCurrentPageObject();
		$pageObject->moveFileTo($name, $destination, $this->getSession());
	}

	/**
	 * @When the user moves the following file/folder using the webUI
	 * @Given the user has moved the following file/folder using the webUI
	 *
	 * @param TableNode $namePartsTable table of parts of the from and to file names
	 *                                  table headings: must be:
	 *                                  |item-to-move-name-parts |destination-name-parts |
	 *
	 * @return void
	 */
	public function theUserMovesTheFollowingFileFolderUsingTheWebUI(
		TableNode $namePartsTable
	) {
		$itemToMoveNameParts = [];
		$destinationNameParts = [];

		foreach ($namePartsTable as $namePartsRow) {
			$itemToMoveNameParts[] = $namePartsRow['item-to-move-name-parts'];
			$destinationNameParts[] = $namePartsRow['destination-name-parts'];
		}
		$this->theUserMovesFileFolderIntoFolderUsingTheWebUI(
			$itemToMoveNameParts, $destinationNameParts
		);
	}

	/**
	 * @When the user batch moves these files/folders into folder :folderName using the webUI
	 * @Given the user has batch moved these files/folders into folder :folderName using the webUI
	 *
	 * @param string $folderName
	 * @param TableNode $files table of file names
	 *                         table headings: must be: |name|
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserBatchMovesTheseFilesIntoFolderUsingTheWebUI(
		$folderName, TableNode $files
	) {
		$this->theUserMarksTheseFilesForBatchActionUsingTheWebUI($files);
		$firstFileName = $files->getRow(1)[0];
		$this->theUserMovesFileFolderIntoFolderUsingTheWebUI(
			$firstFileName, $folderName
		);
		$this->movedElementsTable = $files;
	}

	/**
	 * @When the user uploads overwriting file :name using the webUI
	 * @Given the user has uploaded overwriting file :name using the webUI
	 *
	 * @param string $name
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserUploadsOverwritingFileUsingTheWebUI($name) {
		$this->theUserUploadsFileUsingTheWebUI($name);
		$this->choiceInUploadConflictDialogWebUI("new");
		$this->theUserChoosesToInTheUploadDialog("Continue");
	}

	/**
	 * @When the user uploads overwriting file :name using the webUI and retries if the file is locked
	 * @Given the user has uploaded overwriting file :name using the webUI and retries if the file is locked
	 *
	 * @param string $name
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserUploadsOverwritingFileUsingTheWebUIRetry($name) {
		$previousNotificationsCount = 0;

		for ($retryCounter = 0;
			 $retryCounter < STANDARD_RETRY_COUNT;
			 $retryCounter++) {
			$this->theUserUploadsOverwritingFileUsingTheWebUI($name);

			try {
				$notifications = $this->getCurrentPageObject()->getNotifications();
			} catch (ElementNotFoundException $e) {
				$notifications = [];
			}

			$currentNotificationsCount = \count($notifications);

			if ($currentNotificationsCount > $previousNotificationsCount) {
				$message
					= "Upload overwriting $name" .
					  " and got $currentNotificationsCount" .
					  " notifications including " .
					  \end($notifications) . "\n";
				echo $message;
				\error_log($message);
				$previousNotificationsCount = $currentNotificationsCount;
				\usleep(STANDARD_SLEEP_TIME_MICROSEC);
			} else {
				break;
			}
		}

		if ($retryCounter > 0) {
			$message
				= "INFORMATION: retried to upload overwriting file $name $retryCounter times";
			echo $message;
			\error_log($message);
		}
	}

	/**
	 * @When the user uploads file :name keeping both new and existing files using the webUI
	 * @Given the user has uploaded file :name keeping both new and existing files using the webUI
	 *
	 * @param string $name
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserUploadsFileKeepingNewExistingUsingTheWebUI($name) {
		$this->theUserUploadsFileUsingTheWebUI($name);
		$this->choiceInUploadConflictDialogWebUI("new");
		$this->choiceInUploadConflictDialogWebUI("existing");
		$this->theUserChoosesToInTheUploadDialog("Continue");
	}

	/**
	 * @When the user uploads file :name using the webUI
	 * @Given the user has uploaded file :name using the webUI
	 *
	 * @param string $name
	 *
	 * @return void
	 */
	public function theUserUploadsFileUsingTheWebUI($name) {
		$this->getCurrentPageObject()->uploadFile($this->getSession(), $name);
	}

	/**
	 * @When /^the user chooses to keep the (new|existing) files in the upload dialog$/
	 * @Given /^the user has chosen to keep the (new|existing) files in the upload dialog$/
	 *
	 * @param string $choice
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function choiceInUploadConflictDialogWebUI($choice) {
		$dialogs = $this->getCurrentPageObject()->getOcDialogs();
		$isConflictDialog = false;
		foreach ($dialogs as $dialog) {
			$isConflictDialog = \strstr(
				$dialog->getTitle(), $this->uploadConflictDialogTitle
			);
			if ($isConflictDialog !== false) {
				$this->conflictDialog->setElement($dialog->getOwnElement());
				break;
			}
		}
		if ($isConflictDialog === false) {
			throw new Exception(
				__METHOD__ .
				" file upload conflict dialog expected but not found"
			);
		}
		if ($choice === "new") {
			$this->conflictDialog->keepNewFiles();
		} elseif ($choice === "existing") {
			$this->conflictDialog->keepExistingFiles();
		} else {
			throw new Exception(
				__METHOD__ .
				" the choice can only be 'new' or 'existing'"
			);
		}
	}

	/**
	 * @When the user chooses :label in the upload dialog
	 * @When I click the :label button
	 * @Given the user has chosen :label in the upload dialog
	 * @Given I have clicked the :label button
	 *
	 * @param string $label
	 *
	 * @return void
	 */
	public function theUserChoosesToInTheUploadDialog($label) {
		$pageObject = $this->getCurrentPageObject();
		$dialogs = $pageObject->getOcDialogs();
		$dialog = \end($dialogs);
		$this->conflictDialog->setElement($dialog->getOwnElement());
		$this->conflictDialog->clickButton($this->getSession(), $label);
		$pageObject->waitForUploadProgressbarToFinish();
	}

	/**
	 * @Then /^the (?:deleted|moved) elements should (not|)\s?be listed on the webUI$/
	 *
	 * @param string $shouldOrNot
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theDeletedMovedElementsShouldBeListedOnTheWebUI($shouldOrNot) {
		if ($this->deletedElementsTable !== null) {
			foreach ($this->deletedElementsTable as $file) {
				$this->checkIfFileFolderIsListedOnTheWebUI(
					$file['name'], $shouldOrNot
				);
			}
		}
		if ($this->movedElementsTable !== null) {
			foreach ($this->movedElementsTable as $file) {
				$this->checkIfFileFolderIsListedOnTheWebUI(
					$file['name'], $shouldOrNot
				);
			}
		}
	}

	/**
	 * @Then /^the (?:deleted|moved) elements should (not|)\s?be listed on the webUI after a page reload$/
	 *
	 * @param string $shouldOrNot
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theDeletedMovedElementsShouldBeListedOnTheWebUIAfterPageReload(
		$shouldOrNot
	) {
		$this->webUIGeneralContext->theUserReloadsTheCurrentPageOfTheWebUI();
		$this->theDeletedMovedElementsShouldBeListedOnTheWebUI($shouldOrNot);
	}

	/**
	 * @When the user opens the sharing tab from the file action menu of file/folder :entryName using the webUI
	 *
	 * @param string $entryName
	 *
	 * @return void
	 */
	public function theUserOpensTheSharingTabFromTheActionMenuOfFileUsingTheWebui($entryName) {
		$this->theUserOpensTheFileActionMenuOfFileFolderInTheWebui($entryName);
		$this->theUserClicksTheFileActionInTheWebui("details");
		$this->theUserSwitchesToTabInDetailsPanelUsingTheWebui("sharing");
		$this->filesPage->waitForAjaxCallsToStartAndFinish($this->getSession());
	}

	/**
	 * @Then the deleted elements should be listed in the trashbin on the webUI
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theDeletedElementsShouldBeListedInTheTrashbinOnTheWebUI() {
		$this->theUserBrowsesToTheTrashbinPage();

		foreach ($this->deletedElementsTable as $file) {
			$this->checkIfFileFolderIsListedOnTheWebUI(
				$file['name'], "", "trashbin"
			);
		}
	}

	/**
	 * @Then /^(?:file|folder) ((?:'[^']*')|(?:"[^"]*")) with path ((?:'[^']*')|(?:"[^"]*")) should (not|)\s?be listed\s?(?:in the |)(files page|trashbin|favorites page|shared-with-you page|shared with others page|tags page|)\s?(?:folder ((?:'[^']*')|(?:"[^"]*")))? on the webUI$/
	 *
	 * @param string $name enclosed in single or double quotes
	 * @param string $path
	 * @param string $shouldOrNot
	 * @param string $typeOfFilesPage
	 * @param string $folder
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function fileFolderWithPathShouldBeListedOnTheWebUI(
		$name, $path, $shouldOrNot, $typeOfFilesPage = "", $folder = ""
	) {
		// The capturing groups of the regex include the quotes at each
		// end of the captured string, so trim them.
		if ($folder !== "") {
			$folder = \trim($folder, $folder[0]);
		}
		$path = \trim($path, $path[0]);
		$this->checkIfFileFolderIsListedOnTheWebUI(
			\trim($name, $name[0]),
			$shouldOrNot,
			$typeOfFilesPage,
			$folder,
			$path
		);
	}

	/**
	 * @When the user batch deletes these files using the webUI
	 * @Given the user has batch deleted these files using the webUI
	 *
	 * @param TableNode $files table of file names
	 *                         table headings: must be: |name|
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserBatchDeletesTheseFilesUsingTheWebUI(TableNode $files) {
		$this->deletedElementsTable = $files;
		$this->theUserMarksTheseFilesForBatchActionUsingTheWebUI($files);
		$this->theUserBatchDeletesTheMarkedFilesUsingTheWebUI();
	}

	/**
	 * @When the user batch deletes the marked files using the webUI
	 * @Given the user has batch deleted the marked files using the webUI
	 *
	 * @return void
	 */
	public function theUserBatchDeletesTheMarkedFilesUsingTheWebUI() {
		$pageObject = $this->getCurrentPageObject();
		$pageObject->deleteAllSelectedFiles($this->getSession());
	}

	/**
	 * @When the user batch restores the marked files using the webUI
	 * @Given the user has batch restored the marked files using the webUI
	 *
	 * @return void
	 */
	public function theUserBatchRestoresTheMarkedFilesUsingTheWebUI() {
		$this->trashbinPage->restoreAllSelectedFiles($this->getSession());
	}

	/**
	 * mark a set of files ready for them to be included in a batch action
	 * if any of the files are already marked, then they will be unmarked
	 *
	 * @When the user marks/unmarks these files for batch action using the webUI
	 * @Given the user has marked/unmarked these files for batch action using the webUI
	 *
	 * @param TableNode $files table of file names
	 *                         table headings: must be: |name|
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserMarksTheseFilesForBatchActionUsingTheWebUI(
		TableNode $files
	) {
		$pageObject = $this->getCurrentPageObject();
		$session = $this->getSession();
		$pageObject->waitTillPageIsLoaded($session);
		foreach ($files as $file) {
			$pageObject->selectFileForBatchAction(
				$file['name'], $session
			);
		}
	}

	/**
	 * @When the user marks all files for batch action using the webUI
	 * @Given the user has selected all files for batch action using the webUI
	 *
	 * @return void
	 */
	public function theUserMarksAllFilesForBatchActionUsingTheWebUI() {
		$pageObject = $this->getCurrentPageObject();
		$pageObject->selectAllFilesForBatchAction();
	}

	/**
	 * @When /^the user opens (trashbin|)\s?(file|folder) ((?:'[^']*')|(?:"[^"]*")) using the webUI$/
	 * @Given /^the user has opened (trashbin|)\s?(file|folder) ((?:'[^']*')|(?:"[^"]*")) using the webUI$/
	 *
	 * @param string $typeOfFilesPage
	 * @param string $fileOrFolder
	 * @param string $name enclosed in single or double quotes
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserOpensFolderNamedUsingTheWebUI(
		$typeOfFilesPage, $fileOrFolder, $name
	) {
		// The capturing groups of the regex include the quotes at each
		// end of the captured string, so trim them.
		$this->theUserOpensTheFileOrFolderUsingTheWebUI(
			$typeOfFilesPage, $fileOrFolder, \trim($name, $name[0])
		);
	}

	/**
	 * Open a file or folder in the current folder, or in a path down from the
	 * current folder.
	 *
	 * @param string $typeOfFilesPage
	 * @param string $fileOrFolder "file" or "folder" - the type of the final item
	 *                             to open
	 * @param string|array $relativePath the path from the currently open folder
	 *                                   down to and including the file or folder
	 *                                   to open
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserOpensTheFileOrFolderUsingTheWebUI(
		$typeOfFilesPage, $fileOrFolder, $relativePath
	) {
		if ($typeOfFilesPage === "trashbin") {
			$this->theUserBrowsesToTheTrashbinPage();
		}

		$pageObject = $this->getCurrentPageObject();

		if (\is_array($relativePath)) {
			// Store the single full concatenated file or folder name.
			$breadCrumbs[] = \implode($relativePath);
			// The passed-in path is itself an array of pieces of a single file
			// or folder name. That is done when the file or folder name contains
			// both single and double quotes. The pieces of the file or folder
			// name need to be passed through to openFile still in array form.
			$breadCrumbsForOpenFile[] = $relativePath;
		} else {
			// The passed-in path is a single string representing the path to
			// the item to be opened. Each folder along the way is delimited
			// by "/". Explode it into an array of items to be opened.
			$breadCrumbs = \explode('/', \ltrim($relativePath, '/'));
			$breadCrumbsForOpenFile = $breadCrumbs;
		}

		foreach ($breadCrumbsForOpenFile as $breadCrumb) {
			$pageObject->openFile($breadCrumb, $this->getSession());
			$pageObject->waitTillPageIsLoaded($this->getSession());
		}

		if ($fileOrFolder !== "folder") {
			// Pop the file name off the end of the array of breadcrumbs
			\array_pop($breadCrumbs);
		}

		if (\count($breadCrumbs)) {
			$this->currentFolder .= "/" . \implode('/', $breadCrumbs);
		}
	}

	/**
	 * @Then /^the folder should (not|)\s?be empty on the webUI$/
	 *
	 * @param string $shouldOrNot
	 *
	 * @return void
	 */
	public function theFolderShouldBeEmptyOnTheWebUI($shouldOrNot) {
		$should = ($shouldOrNot !== "not");
		$pageObject = $this->getCurrentPageObject();
		$folderIsEmpty = $pageObject->isFolderEmpty($this->getSession());

		if ($should) {
			PHPUnit_Framework_Assert::assertTrue(
				$folderIsEmpty,
				"folder contains items but should be empty"
			);
		} else {
			PHPUnit_Framework_Assert::assertFalse(
				$folderIsEmpty,
				"folder is empty but should contain items"
			);
		}
	}

	/**
	 * @Then /^the folder should (not|)\s?be empty on the webUI after a page reload$/
	 *
	 * @param string $shouldOrNot
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theFolderShouldBeEmptyOnTheWebUIAfterAPageReload($shouldOrNot) {
		$this->webUIGeneralContext->theUserReloadsTheCurrentPageOfTheWebUI();
		$this->theFolderShouldBeEmptyOnTheWebUI($shouldOrNot);
	}

	/**
	 * @Then /^(?:file|folder) ((?:'[^']*')|(?:"[^"]*")) should (not|)\s?be listed\s?(?:in the |in |)(files page|trashbin|favorites page|shared-with-you page|shared-with-others page|)\s?(?:folder ((?:'[^']*')|(?:"[^"]*")))? on the webUI$/
	 *
	 * @param string $name enclosed in single or double quotes
	 * @param string $shouldOrNot
	 * @param string $typeOfFilesPage
	 * @param string $folder
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function fileFolderShouldBeListedOnTheWebUI(
		$name, $shouldOrNot, $typeOfFilesPage = "", $folder = ""
	) {
		// The capturing groups of the regex include the quotes at each
		// end of the captured string, so trim them.
		if ($folder !== "") {
			$folder = \trim($folder, $folder[0]);
		}

		$this->checkIfFileFolderIsListedOnTheWebUI(
			\trim($name, $name[0]),
			$shouldOrNot,
			$typeOfFilesPage,
			$folder
		);
	}

	/**
	 * @param string|array $name
	 * @param string $shouldOrNot
	 * @param string $typeOfFilesPage
	 * @param string $folder
	 * @param string $path if set, name and path (shown in the webUI) of the file need match
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function checkIfFileFolderIsListedOnTheWebUI(
		$name, $shouldOrNot, $typeOfFilesPage = "", $folder = "", $path = ""
	) {
		$should = ($shouldOrNot !== "not");
		$exceptionMessage = null;
		switch ($typeOfFilesPage) {
			case "files page":
				$this->theUserBrowsesToTheFilesPage();
				break;
			case "trashbin":
				$this->theUserBrowsesToTheTrashbinPage();
				break;
			case "favorites page":
				$this->theUserBrowsesToTheFavoritesPage();
				break;
			case "shared-with-you page":
				$this->theUserBrowsesToTheSharedWithYouPage();
				break;
			case "shared-by-link page":
				$this->theUserBrowsesToTheSharedByLinkPage();
				break;
			case "shared with others page":
			case "shared-with-others page":
				$this->theUserBrowsesToTheSharedWithOthersPage();
				break;
			case "tags page":
				break;
			case "search results page":
				//nothing to do here, we cannot navigate to that page, except by performing a search
				break;
		}
		/**
		 *
		 * @var FilesPageBasic $pageObject
		 */
		$pageObject = $this->getCurrentPageObject();
		$pageObject->waitTillPageIsLoaded($this->getSession());
		if ($folder !== "") {
			$this->theUserOpensTheFileOrFolderUsingTheWebUI(
				$typeOfFilesPage, "folder", $folder
			);
		}

		try {
			if ($path === "") {
				/**
				 *
				 * @var FileRow $fileRow
				 */
				$fileRow = $pageObject->findFileRowByName(
					$name, $this->getSession()
				);
			} else {
				/**
				 *
				 * @var FileRow $fileRow
				 */
				$fileRow = $pageObject->findFileRowByNameAndPath(
					$name, $path, $this->getSession()
				);
			}
			$exceptionMessage = '';
		} catch (ElementNotFoundException $e) {
			$exceptionMessage = $e->getMessage();
			$fileRow = null;
		}

		if (\is_array($name)) {
			$nameText = \implode($name);
		} else {
			$nameText = $name;
		}

		$fileLocationText = " file '$nameText'";

		if ($path !== "") {
			$fileLocationText .= " with path '$path'";
		}

		if ($folder !== "") {
			$fileLocationText .= " in folder '$folder'";
		} else {
			$fileLocationText .= " in current folder";
		}

		if ($typeOfFilesPage !== "") {
			$fileLocationText .= " in $typeOfFilesPage";
		}

		if ($should) {
			PHPUnit_Framework_Assert::assertNotNull(
				$fileRow,
				"could not find $fileLocationText when it should be listed"
			);
			PHPUnit_Framework_Assert::assertTrue(
				$fileRow->isVisible(),
				"file row of $fileLocationText is not visible but should"
			);
		} else {
			if (\is_array($name)) {
				$name = \implode($name);
			}
			if ($fileRow === null) {
				PHPUnit_Framework_Assert::assertContains(
					"could not find file with the name '$name'",
					$exceptionMessage,
					"found $fileLocationText when it should not be listed"
				);
			} else {
				PHPUnit_Framework_Assert::assertFalse(
					$fileRow->isVisible(),
					"file row of $fileLocationText is visible but should not"
				);
			}
		}
	}

	/**
	 * @Then /^the moved elements should (not|)\s?be listed in folder ['"](.*)['"] on the webUI$/
	 *
	 * @param string $shouldOrNot
	 * @param string $folderName
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theMovedElementsShouldBeListedInFolderOnTheWebUI(
		$shouldOrNot, $folderName
	) {
		$this->theUserOpensTheFileOrFolderUsingTheWebUI("", "folder", $folderName);
		$this->getCurrentPageObject()->waitTillPageIsLoaded($this->getSession());
		$this->theDeletedMovedElementsShouldBeListedOnTheWebUI($shouldOrNot);
	}

	/**
	 * @Then /^the following (?:file|folder|item) should (not|)\s?be listed in the following folder on the webUI$/
	 *
	 * @param string $shouldOrNot
	 * @param TableNode $namePartsTable table of parts of the file name
	 *                                  table headings: must be: | item-name-parts | folder-name-parts |
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theFollowingFileFolderShouldBeListedInTheFollowingFolderOnTheWebUI(
		$shouldOrNot, TableNode $namePartsTable
	) {
		$toBeListedTableArray[] = ["name-parts"];
		$folderNameParts = [];
		foreach ($namePartsTable as $namePartsRow) {
			$folderNameParts[] = $namePartsRow['folder-name-parts'];
			$toBeListedTableArray[] = [$namePartsRow['item-name-parts']];
		}
		$this->theUserOpensTheFileOrFolderUsingTheWebUI("", "folder", $folderNameParts);
		$this->getCurrentPageObject()->waitTillPageIsLoaded($this->getSession());

		$toBeListedTable = new TableNode($toBeListedTableArray);
		$this->theFollowingFileFolderShouldBeListedOnTheWebUI(
			$shouldOrNot, "", "", $toBeListedTable
		);
	}

	/**
	 * @Then /^the following (?:file|folder) should (not|)\s?be listed\s?(?:in the |)(files page|trashbin|favorites page|shared-with-you page|)\s?(?:folder ((?:'[^']*')|(?:"[^"]*")))? on the webUI$/
	 *
	 * @param string $shouldOrNot
	 * @param string $typeOfFilesPage
	 * @param string $folder
	 * @param TableNode $namePartsTable table of parts of the file name
	 *                                  table headings: must be: |name-parts |
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theFollowingFileFolderShouldBeListedOnTheWebUI(
		$shouldOrNot,
		$typeOfFilesPage,
		$folder = "",
		TableNode $namePartsTable = null
	) {
		$fileNameParts = [];

		if ($namePartsTable !== null) {
			foreach ($namePartsTable as $namePartsRow) {
				$fileNameParts[] = $namePartsRow['name-parts'];
			}
		} else {
			PHPUnit_Framework_Assert::fail(
				'no table of file name parts passed to theFollowingFileFolderShouldBeListed'
			);
		}

		// The capturing groups of the regex include the quotes at each
		// end of the captured string, so trim them.
		if ($folder !== "") {
			$folder = \trim($folder, $folder[0]);
		}

		$this->checkIfFileFolderIsListedOnTheWebUI(
			$fileNameParts,
			$shouldOrNot,
			$typeOfFilesPage,
			$folder
		);
	}

	/**
	 * @Then near file/folder :name a tooltip with the text :toolTipText should be displayed on the webUI
	 *
	 * @param string $name
	 * @param string $toolTipText
	 *
	 * @return void
	 */
	public function nearFileATooltipWithTheTextShouldBeDisplayedOnTheWebUI(
		$name,
		$toolTipText
	) {
		PHPUnit_Framework_Assert::assertEquals(
			$toolTipText,
			$this->getCurrentPageObject()->getTooltipOfFile($name, $this->getSession())
		);
	}
	
	/**
	 * @When the user restores file/folder :fname from the trashbin using the webUI
	 * @Given the user has restored file/folder :fname from the trashbin using the webUI
	 *
	 * @param string $fname
	 *
	 * @return void
	 */
	public function theUserRestoresFileFolderFromTheTrashbinUsingTheWebUI($fname) {
		$session = $this->getSession();
		$this->trashbinPage->restore($fname, $session);
	}

	/**
	 * @Then near the folder input field a tooltip with the text :tooltiptext should be displayed on the webUI
	 *
	 * @param string $tooltiptext
	 *
	 * @return void
	 */
	public function folderInputFieldTooltipTextShouldBeDisplayedOnTheWebUI(
		$tooltiptext
	) {
		$createFolderTooltip = $this->getCurrentPageObject()->getCreateFolderTooltip();
		PHPUnit_Framework_Assert::assertSame($tooltiptext, $createFolderTooltip);
	}

	/**
	 * @Then it should not be possible to delete file/folder :name using the webUI
	 *
	 * @param string $name
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function itShouldNotBePossibleToDeleteFileFolderUsingTheWebUI($name) {
		try {
			$this->deleteTheFileUsingTheWebUI($name, false);
		} catch (ElementNotFoundException $e) {
			PHPUnit_Framework_Assert::assertContains(
				"could not find button 'Delete' in action Menu",
				$e->getMessage()
			);
		}
	}

	/**
	 * @Then the files action menu should be completely visible after opening it using the webUI
	 *
	 * @return void
	 */
	public function theFilesActionMenuShouldBeCompletelyVisibleAfterOpeningItUsingTheWebUI() {
		for ($i = 1; $i <= $this->filesPage->getSizeOfFileFolderList(); $i++) {
			$actionMenu = $this->filesPage->openFileActionsMenuByNo(
				$i, $this->getSession()
			);
			
			$timeout_msec = STANDARD_UI_WAIT_TIMEOUT_MILLISEC;
			$currentTime = \microtime(true);
			$end = $currentTime + ($timeout_msec / 1000);
			while ($currentTime <= $end) {
				$windowHeight = $this->filesPage->getWindowHeight(
					$this->getSession()
				);
				
				$deleteBtn = $actionMenu->findButton(
					$actionMenu->getDeleteActionLabel()
				);
				$deleteBtnCoordinates = $this->filesPage->getCoordinatesOfElement(
					$this->getSession(), $deleteBtn
				);
				if ($windowHeight >= $deleteBtnCoordinates ["top"]) {
					break;
				}
				\usleep(STANDARD_SLEEP_TIME_MICROSEC);
				$currentTime = \microtime(true);
			}
			
			PHPUnit_Framework_Assert::assertLessThanOrEqual(
				$windowHeight, $deleteBtnCoordinates ["top"]
			);
			//this will close the menu again
			$this->filesPage->clickFileActionsMenuBtnByNo($i);
		}
	}

	/**
	 * @Then /^the content of ((?:'[^']*')|(?:"[^"]*")) (on the remote server|on the local server|)\s?should (not|)\s?be the same as the original ((?:'[^']*')|(?:"[^"]*"))$/
	 *
	 * @param string $remoteFile enclosed in single or double quotes
	 * @param string $remoteServer
	 * @param string $shouldOrNot
	 * @param string $originalFile enclosed in single or double quotes
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theContentOfShouldBeTheSameAsTheOriginal(
		$remoteFile, $remoteServer, $shouldOrNot, $originalFile
	) {
		$checkOnRemoteServer = ($remoteServer === 'on the remote server');
		// The capturing group of the regex always includes the quotes at each
		// end of the captured string, so trim them.
		$remoteFile = $this->currentFolder . "/" . \trim($remoteFile, $remoteFile[0]);
		$originalFile = \trim($originalFile, $originalFile[0]);

		$shouldBeSame = ($shouldOrNot !== "not");
		$this->assertContentOfDAVFileAndSkeletonFileOnSUT(
			$remoteFile, $originalFile, $shouldBeSame, $checkOnRemoteServer
		);
	}

	/**
	 * @Then /^the content of ((?:'[^']*')|(?:"[^"]*")) (on the remote server|on the local server|)\s?should (not|)\s?be the same as the local ((?:'[^']*')|(?:"[^"]*"))$/
	 *
	 * @param string $remoteFile enclosed in single or double quotes
	 * @param string $remoteServer
	 * @param string $shouldOrNot
	 * @param string $localFile enclosed in single or double quotes
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theContentOfShouldBeTheSameAsTheLocal(
		$remoteFile, $remoteServer, $shouldOrNot, $localFile
	) {
		$checkOnRemoteServer = ($remoteServer === 'on the remote server');
		// The capturing group of the regex always includes the quotes at each
		// end of the captured string, so trim them.
		$remoteFile = $this->currentFolder . "/" . \trim($remoteFile, $remoteFile[0]);
		$localFile = \getenv("FILES_FOR_UPLOAD") . "/" . \trim($localFile, $localFile[0]);
		$shouldBeSame = ($shouldOrNot !== "not");
		$this->assertContentOfRemoteAndLocalFileIsSame(
			$remoteFile, $localFile, $shouldBeSame, $checkOnRemoteServer
		);
	}

	/**
	 * @Then /^the content of ((?:'[^']*')|(?:"[^"]*")) (on the remote server|on the local server|)\s?should not have changed$/
	 *
	 * @param string $fileName
	 * @param string $remoteServer
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theContentOfShouldNotHaveChanged($fileName, $remoteServer) {
		$checkOnRemoteServer = ($remoteServer === 'on the remote server');
		// The capturing group of the regex always includes the quotes at each
		// end of the captured string, so trim them.
		$fileName = \trim($fileName, $fileName[0]);
		$remoteFile = "$this->currentFolder/$fileName";
		if ($this->currentFolder !== "") {
			$subFolderPath = "$this->currentFolder/";
		} else {
			$subFolderPath = "";
		}
		$localFile = "$subFolderPath$fileName";
		$this->assertContentOfDAVFileAndSkeletonFileOnSUT(
			$remoteFile, $localFile, true, $checkOnRemoteServer
		);
	}

	/**
	 * @When the user marks file/folder :fileOrFolderName as favorite using the webUI
	 * @Given the user has marked file/folder :fileOrFolderName as favorite using the webUI
	 *
	 * @param string $fileOrFolderName
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function theUserMarksFileAsFavoriteUsingTheWebUI($fileOrFolderName) {
		$fileRow = $this->filesPage->findFileRowByName(
			$fileOrFolderName, $this->getSession()
		);
		$fileRow->markAsFavorite();
		$this->filesPage->waitTillFileRowsAreReady($this->getSession());
	}

	/**
	 * @Then file/folder :fileOrFolderName should be marked as favorite on the webUI
	 *
	 * @param string $fileOrFolderName
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function fileFolderShouldBeMarkedAsFavoriteOnTheWebUI($fileOrFolderName) {
		$fileRow = $this->filesPage->findFileRowByName(
			$fileOrFolderName, $this->getSession()
		);
		if ($fileRow->isMarkedAsFavorite() === false) {
			throw new Exception(
				__METHOD__ .
				" The file $fileOrFolderName is not marked as favorite but should be"
			);
		}
	}
	
	/**
	 * @When the user unmarks the favorited file/folder :fileOrFolderName using the webUI
	 * @Given the user has unmarked the favorited file/folder :fileOrFolderName using the webUI
	 *
	 * @param string $fileOrFolderName
	 *
	 * @return void
	 */
	public function theUserUnmarksTheFavoritedFileUsingTheWebUI($fileOrFolderName) {
		$fileRow = $this->getCurrentPageObject()->findFileRowByName(
			$fileOrFolderName, $this->getSession()
		);
		$fileRow->unmarkFavorite();
		$this->getCurrentPageObject()->waitTillFileRowsAreReady($this->getSession());
	}

	/**
	 * @Then file/folder :fileOrFolderName should not be marked as favorite on the webUI
	 *
	 * @param string $fileOrFolderName
	 *
	 * @return void
	 * @throws \Exception
	 */
	public function fileFolderShouldNotBeMarkedAsFavoriteOnTheWebUI(
		$fileOrFolderName
	) {
		$fileRow = $this->filesPage->findFileRowByName(
			$fileOrFolderName, $this->getSession()
		);
		if ($fileRow->isMarkedAsFavorite() === true) {
			throw new Exception(
				__METHOD__ .
				" The file $fileOrFolderName is marked as favorite but should not be"
			);
		}
	}

	/**
	 * @see WebDavAssert::assertContentOfRemoteAndLocalFileIsSame
	 * uses the current user to download the remote file
	 *
	 * @param string $remoteFile
	 * @param string $localFile
	 * @param bool $shouldBeSame (default true) if true then check that the file contents are the same
	 *                           otherwise check that the file contents are different
	 * @param bool $checkOnRemoteServer if true, then use the remote server to download the file
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function assertContentOfRemoteAndLocalFileIsSame(
		$remoteFile, $localFile, $shouldBeSame = true, $checkOnRemoteServer = false
	) {
		if ($checkOnRemoteServer) {
			$baseUrl = $this->featureContext->getRemoteBaseUrl();
		} else {
			$baseUrl = $this->featureContext->getLocalBaseUrl();
		}
		
		$username = $this->featureContext->getCurrentUser();
		WebDavAssert::assertContentOfRemoteAndLocalFileIsSame(
			$baseUrl,
			$username,
			$this->featureContext->getUserPassword($username),
			$remoteFile,
			$localFile,
			$shouldBeSame
		);
	}

	/**
	 * @see WebDavAssert::assertContentOfDAVFileAndSkeletonFileOnSUT
	 * uses the current user to download the remote file
	 *
	 * @param string $remoteFile
	 * @param string $fileInSkeletonFolder
	 * @param bool $shouldBeSame (default true) if true then check that the file contents are the same
	 *                           otherwise check that the file contents are different
	 * @param bool $checkOnRemoteServer if true, then use the remote server to download the file
	 *
	 * @return void
	 * @throws \Exception
	 */
	private function assertContentOfDAVFileAndSkeletonFileOnSUT(
		$remoteFile,
		$fileInSkeletonFolder,
		$shouldBeSame = true,
		$checkOnRemoteServer = false
	) {
		if ($checkOnRemoteServer) {
			$baseUrl = $this->featureContext->getRemoteBaseUrl();
		} else {
			$baseUrl = $this->featureContext->getLocalBaseUrl();
		}

		$username = $this->featureContext->getCurrentUser();
		WebDavAssert::assertContentOfDAVFileAndSkeletonFileOnSUT(
			$baseUrl,
			$username,
			$this->featureContext->getUserPassword($username),
			$this->featureContext->getAdminUsername(),
			$this->featureContext->getAdminPassword(),
			$remoteFile,
			$fileInSkeletonFolder,
			$shouldBeSame
		);
	}

	/**
	 * This will run before EVERY scenario.
	 * It will set the properties for this object.
	 *
	 * @BeforeScenario @webUI
	 *
	 * @param BeforeScenarioScope $scope
	 *
	 * @return void
	 */
	public function before(BeforeScenarioScope $scope) {
		// Get the environment
		$environment = $scope->getEnvironment();
		// Get all the contexts you need in this context
		$this->featureContext = $environment->getContext('FeatureContext');
		$this->webUIGeneralContext = $environment->getContext('WebUIGeneralContext');
	}

	/**
	 * @When the user enables the setting to view hidden files/folders on the webUI
	 * @Given the user has enabled the setting to view hidden files/folders on the webUI
	 * @return void
	 */
	public function theUserEnablesTheSettingToViewHiddenFoldersOnTheWebUI() {
		$this->filesPage->enableShowHiddenFilesSettings();
	}

	/**
	 * @When the user opens the file action menu of file/folder :name in the webUI
	 *
	 * @param string $name Name of the file/Folder
	 *
	 * @return void
	 */
	public function theUserOpensTheFileActionMenuOfFileFolderInTheWebui($name) {
		$session = $this->getSession();
		$this->selectedFileRow = $this->getCurrentPageObject()->findFileRowByName($name, $session);
		$this->openedFileActionMenu = $this->selectedFileRow->openFileActionsMenu($session);
	}

	/**
	 * @Then the user should see :action_label file action translated to :translated_label in the webUI
	 *
	 * @param string $action_label
	 * @param string $translated_label
	 *
	 * @return void
	 */
	public function theUserShouldSeeFileActionTranslatedToInTheWebui($action_label, $translated_label) {
		PHPUnit_Framework_Assert::assertSame(
			$translated_label,
			$this->openedFileActionMenu->getActionLabelLocalized($action_label)
		);
	}

	/**
	 * @When the user clicks the :action_label file action in the webUI
	 *
	 * @param string $action_label
	 *
	 * @throws \Exception
	 * @return void
	 */
	public function theUserClicksTheFileActionInTheWebui($action_label) {
		switch ($action_label) {
			case "details":
				$this->openedFileActionMenu->openDetails();
				$this->getCurrentPageObject()
					->getDetailsDialog()
					->waitTillPageIsLoaded($this->getSession());
				break;
			case "rename":
				$this->openedFileActionMenu->rename();
				break;
			case "delete":
				$this->openedFileActionMenu->delete();
				break;
			case "search results page":
				throw new Exception("Action not available");
				break;
		}
	}

	/**
	 * @Then the details dialog should be visible in the webUI
	 *
	 * @return void
	 */
	public function theDetailsDialogShouldBeVisibleInTheWebui() {
		PHPUnit_Framework_Assert::assertTrue($this->filesPage->getDetailsDialog()->isDialogVisible());
	}

	/**
	 * @When the user switches to :tabName tab in details panel using the webUI
	 *
	 * @param string $tabName
	 *
	 * @return void
	 */
	public function theUserSwitchesToTabInDetailsPanelUsingTheWebui($tabName) {
		$this->filesPage->getDetailsDialog()->changeDetailsTab($tabName);
	}

	/**
	 * @When the user comments with content :content using the WebUI
	 *
	 * @param string $content
	 *
	 * @return void
	 */
	public function theUserCommentsWithContentUsingTheWebui($content) {
		$detailsDialog = $this->filesPage->getDetailsDialog();
		$detailsDialog->addComment($this->getSession(), $content);
		$this->filesPage->waitForAjaxCallsToStartAndFinish($this->getSession());
	}

	/**
	 * @When the user deletes the comment :content using the webUI
	 *
	 * @param string $content
	 *
	 * @return void
	 */
	public function theUserDeletesTheCommentUsingTheWebui($content) {
		$detailsDialog = $this->filesPage->getDetailsDialog();
		$detailsDialog->deleteComment($content);
		$this->filesPage->waitForAjaxCallsToStartAndFinish($this->getSession());
	}

	/**
	 * @Then /^the comment ((?:'[^']*')|(?:"[^"]*")) should (not|)\s?be listed in the comments tab in details dialog$/
	 *
	 * @param string $text enclosed in single or double quotes
	 * @param string $shouldOrNot
	 *
	 * @return void
	 */
	public function theCommentShouldBeListedInTheCommentsTabInDetailsDialog($text, $shouldOrNot) {
		$should = ($shouldOrNot !== "not");
		$text = \trim($text, $text[0]);
		/**
		 *
		 * @var DetailsDialog $detailsDialog
		 */
		$detailsDialog = $this->getCurrentPageObject()->getDetailsDialog();
		$detailsDialog->waitTillPageIsLoaded($this->getSession());
		if ($should) {
			PHPUnit_Framework_Assert::assertTrue(
				$detailsDialog->isCommentOnUI($text),
				"Failed to find comment with text $text in the webUI"
			);
		} else {
			PHPUnit_Framework_Assert::assertFalse(
				$detailsDialog->isCommentOnUI($text),
				"The comment with text $text exists in the webUI"
			);
		}
	}

	/**
	 * @When the user searches for tag :tag using the webUI
	 *
	 * @param string $tag
	 *
	 * @return void
	 */
	public function theUserSearchesForTagUsingTheWebui($tag) {
		$this->tagsPage->searchByTag($tag);
	}

	/**
	 * @When the user closes the details dialog
	 *
	 * @return void
	 */
	public function theUserClosesTheDetailsDialog() {
		$this->filesPage->closeDetailsDialog();
	}

	/**
	 * @Then the versions list should contain :num entries
	 *
	 * @param int $num
	 *
	 * @return void
	 */
	public function theVersionsListShouldContainEntries($num) {
		$versionsList = $this->filesPage->getDetailsDialog()->getVersionsList();
		$versionsCount = \count($versionsList->findAll("xpath", "//li"));
		PHPUnit_Framework_Assert::assertEquals($num, $versionsCount);
	}

	/**
	 * @When the user restores the file to last version using the webUI
	 *
	 * @return void
	 */
	public function theUserRestoresTheFileToLastVersionUsingTheWebui() {
		$this->filesPage->getDetailsDialog()->restoreCurrentFileToLastVersion();
	}
}