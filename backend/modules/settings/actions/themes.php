<?php

/*
 * This file is part of Fork CMS.
 *
 * For the full copyright and license information, please view the license
 * file that was distributed with this source code.
 */

/**
 * This is the themes-action, it will display a form to set theme settings
 *
 * @author Tijs Verkoyen <tijs@sumocoders.be>
 * @author Matthias Mullie <matthias@mullie.eu>
 */
class BackendSettingsThemes extends BackendBaseActionIndex
{
	/**
	 * The form instance
	 *
	 * @var	BackendForm
	 */
	private $frm;

	/**
	 * Execute the action
	 */
	public function execute()
	{
		parent::execute();
		$this->loadForm();
		$this->validateForm();
		$this->parse();
		$this->display();
	}

	/**
	 * Load the form
	 */
	private function loadForm()
	{
		$this->frm = new BackendForm('settingsThemes');
		$this->frm->addDropdown('theme', BackendModel::getThemes(), BackendModel::getModuleSetting('core', 'theme', 'core'));
	}

	/**
	 * Parse the form
	 */
	private function parse()
	{
		$this->frm->parse($this->tpl);
	}

	/**
	 * Validates the form
	 */
	private function validateForm()
	{
		// is the form submitted?
		if($this->frm->isSubmitted())
		{
			// no errors?
			if($this->frm->isCorrect())
			{
				// determine themes
				$newTheme = $this->frm->getField('theme')->getValue();
				$oldTheme = BackendModel::getModuleSetting('core', 'theme', 'core');

				// check if we actually switched themes
				if($newTheme != $oldTheme)
				{
					// fetch templates
					$oldTemplates = BackendPagesModel::getTemplates($oldTheme);
					$newTemplates = BackendPagesModel::getTemplates($newTheme);

					// check if templates already exist
					if(empty($newTemplates))
					{
						// templates do not yet exist; don't switch
						$this->redirect(BackendModel::createURLForAction('themes') . '&error=no-templates-available');
						exit;
					}

					// fetch current default template
					$oldDefaultTemplatePath = $oldTemplates[BackendModel::getModuleSetting('pages', 'default_template')]['path'];

					// loop new templates
					foreach($newTemplates as $newTemplateId => $newTemplate)
					{
						// check if a a similar default template exists
						if($newTemplate['path'] == $oldDefaultTemplatePath)
						{
							// set new default id
							$newDefaultTemplateId = (int) $newTemplateId;
							break;
						}
					}

					// no default template was found, set first template as default
					if(!isset($newDefaultTemplateId))
					{
						$newDefaultTemplateId = array_keys($newTemplates);
						$newDefaultTemplateId = $newDefaultTemplateId[0];
					}

					// update theme
					BackendModel::setModuleSetting('core', 'theme', $newTheme);

					// save new default template
					BackendModel::setModuleSetting('pages', 'default_template', $newDefaultTemplateId);

					// loop old templates
					foreach($oldTemplates as $oldTemplateId => $oldTemplate)
					{
						// loop new templates
						foreach($newTemplates as $newTemplateId => $newTemplate)
						{
							// check if we have a matching template
							if($oldTemplate['path'] == $newTemplate['path'])
							{
								// switch template
								BackendPagesModel::updatePagesTemplates($oldTemplateId, $newTemplateId);

								// break loop
								continue 2;
							}
						}

						// getting here meant we found no matching template for the new theme; pick first theme's template as default
						BackendPagesModel::updatePagesTemplates($oldTemplateId, $newDefaultTemplateId);
					}

					// trigger event
					BackendModel::triggerEvent($this->getModule(), 'after_changed_theme');
				}

				// assign report
				$this->tpl->assign('report', true);
				$this->tpl->assign('reportMessage', BL::msg('Saved'));
			}
		}
	}
}
