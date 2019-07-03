<?php

/**
 * @file plugins/generic/embedHtmlArticleGalley/EmbedHtmlArticleGalleyPlugin.inc.php
 *
 * Copyright (c) 2014-2019 Simon Fraser University
 * Copyright (c) 2003-2019 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class EmbedHtmlArticleGalleyPlugin
 * @ingroup plugins_generic_embedHtmlArticleGalley
 *
 * @brief Class for EmbedHtmlArticleGalley plugin
 */

import('lib.pkp.classes.plugins.GenericPlugin');

class EmbedHtmlArticleGalleyPlugin extends GenericPlugin {
	/**
	 * @see Plugin::register()
	 */
	function register($category, $path, $mainContextId = null) {
		if (parent::register($category, $path, $mainContextId)) {
			if ($this->getEnabled($mainContextId)) {
				HookRegistry::register('ArticleHandler::view::galley', array($this, 'articleViewCallback'), HOOK_SEQUENCE_LATE);
				$this->_registerTemplateResource();
			}
			return true;
		}
		return false;
	}

	/**
	 * Get the display name of this plugin.
	 * @return String
	 */
	function getDisplayName() {
		return __('plugins.generic.embedHtmlArticleGalley.displayName');
	}

	/**
	 * Get a description of the plugin.
	 */
	function getDescription() {
		return __('plugins.generic.embedHtmlArticleGalley.description');
	}

	/**
	 * Present the article wrapper page.
	 * @param string $hookName
	 * @param array $args
	 */
	function articleViewCallback($hookName, $args) {
		$request =& $args[0];
		$issue =& $args[1];
		$galley =& $args[2];
		$article =& $args[3];

		if ($galley && $galley->getFileType() == 'text/html') {
			$fileId = $galley->getFileId();
			if (!HookRegistry::call('HtmlArticleGalleyPlugin::articleDownload', array($article,  &$galley, &$fileId))) {
				$templateMgr = TemplateManager::getManager($request);
				$html = $this->_getHTMLContents($request, $galley);
				$doc = new DOMDocument();
				libxml_use_internal_errors(true);

				if (Config::getVar('i18n', 'client_charset') === "utf-8")
					$doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
				else
					$doc->loadHTML($html);

				if ($doc->getElementsByTagName('body')->length != 0) {

					$bodyElement = $doc->getElementsByTagName('body')->item(0);
					$body = "";
					foreach ($bodyElement->childNodes as $childNode) {
					  $body .= $doc->saveHTML($childNode);
					}				

					if ($doc->getElementsByTagName('head')->length != 0) {
						$head = $doc->getElementsByTagName('head')->item(0);
						if ($head->getElementsByTagName('link')->length != 0) {
							$links = $head->getElementsByTagName("link");
							$count = 0;
							foreach($links as $l) {
							    if($l->getAttribute("rel") == "stylesheet") {
							        $templateMgr->addHeader('embedStylesheet'. $count .'', '<link rel="stylesheet" type="text/css" href="' . $l->getAttribute("href") . '">');
							        $count++;
							    }
							}
						}
						if ($head->getElementsByTagName('script')->length != 0) {
							$scripts = $head->getElementsByTagName("script");
							$count = 0;
							foreach($scripts as $script) {
				    				if(stristr($script->getAttribute("src"), '.js')) {
				        				$templateMgr->addHeader('embedJs'. $count .'', '<script type="text/javascript" src="' . $script->getAttribute("src") . '"></script>');
				        				$count++;
								}
				    			}
						}
					}

				} else {
					$body = $doc->savehtml(); 
				}

				$returner = true;
				HookRegistry::call('HtmlArticleGalleyPlugin::articleDownloadFinished', array(&$returner));	
				$templateMgr->assign(array(
					'issue' => $issue,
					'article' => $article,
					'html' => $body,
				));
				$templateMgr->display($this->getTemplateResource('display.tpl'));
				return true;
			}
		}

		return false;
	}

	/**
	 * Return string containing the contents of the HTML file.
	 * This function performs any necessary filtering, like image URL replacement.
	 * @param $request PKPRequest
	 * @param $galley ArticleGalley
	 * @return string
	 */
	function _getHTMLContents($request, $galley) {
		$journal = $request->getJournal();
		$submissionFile = $galley->getFile();
		$contents = file_get_contents($submissionFile->getFilePath());

		// Replace media file references
		$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
		import('lib.pkp.classes.submission.SubmissionFile'); // Constants
		$embeddableFiles = array_merge(
			$submissionFileDao->getLatestRevisions($submissionFile->getSubmissionId(), SUBMISSION_FILE_PROOF),
			$submissionFileDao->getLatestRevisionsByAssocId(ASSOC_TYPE_SUBMISSION_FILE, $submissionFile->getFileId(), $submissionFile->getSubmissionId(), SUBMISSION_FILE_DEPENDENT)
		);
		$referredArticle = null;
		$articleDao = DAORegistry::getDAO('ArticleDAO');

		foreach ($embeddableFiles as $embeddableFile) {
			$params = array();

			if ($embeddableFile->getFileType()=='text/plain' || $embeddableFile->getFileType()=='text/css') $params['inline']='true';

			// Ensure that the $referredArticle object refers to the article we want
			if (!$referredArticle || $referredArticle->getId() != $galley->getSubmissionId()) {
				$referredArticle = $articleDao->getById($galley->getSubmissionId());
			}
			$fileUrl = $request->url(null, 'article', 'download', array($referredArticle->getBestArticleId(), $galley->getBestGalleyId(), $embeddableFile->getFileId()), $params);
			$pattern = preg_quote($embeddableFile->getOriginalFileName());

			$contents = preg_replace(
				'/([Ss][Rr][Cc]|[Hh][Rr][Ee][Ff]|[Dd][Aa][Tt][Aa])\s*=\s*"([^"]*' . $pattern . ')"/',
				'\1="' . $fileUrl . '"',
				$contents
			);

			// Replacement for Flowplayer
			$contents = preg_replace(
				'/[Uu][Rr][Ll]\s*\:\s*\'(' . $pattern . ')\'/',
				'url:\'' . $fileUrl . '\'',
				$contents
			);

			// Replacement for other players (ested with odeo; yahoo and google player won't work w/ OJS URLs, might work for others)
			$contents = preg_replace(
				'/[Uu][Rr][Ll]=([^"]*' . $pattern . ')/',
				'url=' . $fileUrl ,
				$contents
			);

		}

		// Perform replacement for ojs://... URLs
		$contents = preg_replace_callback(
			'/(<[^<>]*")[Oo][Jj][Ss]:\/\/([^"]+)("[^<>]*>)/',
			array($this, '_handleOjsUrl'),
			$contents
		);

		// Perform variable replacement for journal, issue, site info
		$issueDao = DAORegistry::getDAO('IssueDAO');
		$issue = $issueDao->getByArticleId($galley->getSubmissionId());

		$journal = $request->getJournal();
		$site = $request->getSite();

		$paramArray = array(
			'issueTitle' => $issue?$issue->getIssueIdentification():__('editor.article.scheduleForPublication.toBeAssigned'),
			'journalTitle' => $journal->getLocalizedName(),
			'siteTitle' => $site->getLocalizedTitle(),
			'currentUrl' => $request->getRequestUrl()
		);

		foreach ($paramArray as $key => $value) {
			$contents = str_replace('{$' . $key . '}', $value, $contents);
		}

		return $contents;
	}

	function _handleOjsUrl($matchArray) {
		$request = Application::getRequest();
		$url = $matchArray[2];
		$anchor = null;
		if (($i = strpos($url, '#')) !== false) {
			$anchor = substr($url, $i+1);
			$url = substr($url, 0, $i);
		}
		$urlParts = explode('/', $url);
		if (isset($urlParts[0])) switch(strtolower_codesafe($urlParts[0])) {
			case 'journal':
				$url = $request->url(
				isset($urlParts[1]) ?
				$urlParts[1] :
				$request->getRequestedJournalPath(),
				null,
				null,
				null,
				null,
				$anchor
				);
				break;
			case 'article':
				if (isset($urlParts[1])) {
					$url = $request->url(
							null,
							'article',
							'view',
							$urlParts[1],
							null,
							$anchor
					);
				}
				break;
			case 'issue':
				if (isset($urlParts[1])) {
					$url = $request->url(
							null,
							'issue',
							'view',
							$urlParts[1],
							null,
							$anchor
					);
				} else {
					$url = $request->url(
							null,
							'issue',
							'current',
							null,
							null,
							$anchor
					);
				}
				break;
			case 'sitepublic':
				array_shift($urlParts);
				import ('classes.file.PublicFileManager');
				$publicFileManager = new PublicFileManager();
				$url = $request->getBaseUrl() . '/' . $publicFileManager->getSiteFilesPath() . '/' . implode('/', $urlParts) . ($anchor?'#' . $anchor:'');
				break;
			case 'public':
				array_shift($urlParts);
				$journal = $request->getJournal();
				import ('classes.file.PublicFileManager');
				$publicFileManager = new PublicFileManager();
				$url = $request->getBaseUrl() . '/' . $publicFileManager->getJournalFilesPath($journal->getId()) . '/' . implode('/', $urlParts) . ($anchor?'#' . $anchor:'');
				break;
		}
		return $matchArray[1] . $url . $matchArray[3];
	}
}

?>
