<?php

/**
 * @file classes/submission/form/comment/ProofreadCommentForm.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class ProofreadCommentForm
 * @ingroup submission_form
 *
 * @brief ProofreadComment form.
 */



import('classes.submission.form.comment.CommentForm');

class ProofreadCommentForm extends CommentForm {

	/**
	 * Constructor.
	 * @param $monograph object
	 */
	function ProofreadCommentForm($monograph, $roleId) {
		parent::CommentForm($monograph, COMMENT_TYPE_PROOFREAD, $roleId, $monograph->getId());
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('pageTitle', 'submission.comments.corrections');
		$templateMgr->assign('commentAction', 'postProofreadComment');
		$templateMgr->assign('commentType', 'proofread');
		$templateMgr->assign('hiddenFormParams',
			array(
				'monographId' => $this->monograph->getId()
			)
		);

		parent::display();
	}

	/**
	 * Assign form data to user-submitted data.
	 */
	function readInputData() {
		parent::readInputData();
	}

	/**
	 * Add the comment.
	 */
	function execute() {
		parent::execute();
	}

	/**
	 * Email the comment.
	 */
	function email() {
		$roleDao =& DAORegistry::getDAO('RoleDAO');
		$userDao =& DAORegistry::getDAO('UserDAO');
		$press =& Request::getPress();

		// Create list of recipients:
		$recipients = array();

		// Proofread comments are to be sent to the editors, layout editor, proofreader, and author,
		// excluding whomever posted the comment.

		// Get editors
		// FIXME #5880: Get IDs from Monograph->getAssociatedUserIds, or remove this class if not needed
		$editAssignmentDao =& DAORegistry::getDAO('EditAssignmentDAO');
		$editAssignments =& $editAssignmentDao->getByIdsByMonographId($this->monograph->getId());
		$editorAddresses = array();
		while (!$editAssignments->eof()) {
			$editAssignment =& $editAssignments->next();
			if ($editAssignment->getCanEdit()) $editorAddresses[$editAssignment->getEditorEmail()] = $editAssignment->getEditorFullName();
			unset($editAssignment);
		}

		// If no editors are currently assigned to this monograph,
		// send the email to all editors for the press
		if (empty($editorAddresses)) {
			$editors =& $roleDao->getUsersByRoleId(ROLE_ID_EDITOR, $press->getPressId());
			while (!$editors->eof()) {
				$editor =& $editors->next();
				$editorAddresses[$editor->getEmail()] = $editor->getFullName();
			}
		}

		// Get layout editor
		$layoutAssignmentDao =& DAORegistry::getDAO('LayoutAssignmentDAO');
		$layoutAssignment =& $layoutAssignmentDao->getLayoutAssignmentByMonographId($this->monograph->getId());
		if ($layoutAssignment != null && $layoutAssignment->getEditorId() > 0) {
			$layoutEditor =& $userDao->getUser($layoutAssignment->getEditorId());
		} else {
			$layoutEditor = null;
		}

		// Get proofreader
		$proofAssignmentDao =& DAORegistry::getDAO('ProofAssignmentDAO');
		$proofAssignment =& $proofAssignmentDao->getProofAssignmentByMonographId($this->monograph->getId());
		if ($proofAssignment != null && $proofAssignment->getProofreaderId() > 0) {
			$proofreader =& $userDao->getUser($proofAssignment->getProofreaderId());
		} else {
			$proofreader = null;
		}

		// Get author
		$author =& $userDao->getUser($this->monograph->getUserId());

		// Choose who receives this email
		if ($this->roleId == ROLE_ID_EDITOR || $this->roleId == ROLE_ID_SERIES_EDITOR) {
			// Then add layout editor, proofreader and author
			if ($layoutEditor != null) {
				$recipients = array_merge($recipients, array($layoutEditor->getEmail() => $layoutEditor->getFullName()));
			}

			if ($proofreader != null) {
				$recipients = array_merge($recipients, array($proofreader->getEmail() => $proofreader->getFullName()));
			}

			if (isset($author)) $recipients = array_merge($recipients, array($author->getEmail() => $author->getFullName()));

		} else if ($this->roleId == ROLE_ID_LAYOUT_EDITOR) {
			// Then add editors, proofreader and author
			$recipients = array_merge($recipients, $editorAddresses);

			if ($proofreader != null) {
				$recipients = array_merge($recipients, array($proofreader->getEmail() => $proofreader->getFullName()));
			}

			if (isset($author)) $recipients = array_merge($recipients, array($author->getEmail() => $author->getFullName()));

		} else if ($this->roleId == ROLE_ID_PROOFREADER) {
			// Then add editors, layout editor, and author
			$recipients = array_merge($recipients, $editorAddresses);

			if ($layoutEditor != null) {
				$recipients = array_merge($recipients, array($layoutEditor->getEmail() => $layoutEditor->getFullName()));
			}

			if (isset($author)) $recipients = array_merge($recipients, array($author->getEmail() => $author->getFullName()));

		} else {
			// Then add editors, layout editor, and proofreader
			$recipients = array_merge($recipients, $editorAddresses);

			if ($layoutEditor != null) {
				$recipients = array_merge($recipients, array($layoutEditor->getEmail() => $layoutEditor->getFullName()));
			}

			if ($proofreader != null) {
				$recipients = array_merge($recipients, array($proofreader->getEmail() => $proofreader->getFullName()));
			}
		}

		parent::email($recipients);
	}
}

?>
