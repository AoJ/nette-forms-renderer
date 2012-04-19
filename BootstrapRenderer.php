<?php
/**
 * This file has been created within Animal Group
 *
 * @copyright Animal Group
 */

namespace Renderer;

use Nette;
use Nette\Forms\Controls;
use Nette\Templating\FileTemplate;
use Nette\Utils\Html;



/**
 * The base template renderer for forms.
 *
 * Usage:
 * $form->addRenderer(new TemplateFormsRenderer);
 *
 * Created with twitter bootstrap in mind. If you want to append options, use:
 *
 * ok $field->setOption('help', 'help text');
 * ok $field->setOption('status', 'warning|error|success'); // the style of the field
 * ok $field->setOption('class', 'someclasses');
 * ok $field->setOption('prepend', 'prepend text within input');
 * ok $field->setOption('append', 'append text within input');
 * ok $field->setOption('prepend-button', 'buttonid');
 * ok $field->setOption('append-button', 'buttonid');
 * ok $field->setOption('placeholder', 'this is some placeholder text');
 * ok $field->setOption('template', '/resolvable/path/to/template');
 *
 * @author Pavel Ptacek
 * @author Filip Procházka
 */
class BootstrapRenderer extends Nette\Object implements Nette\Forms\IFormRenderer
{

	/**
	 * set to false, if you want to display the field errors also as form errors
	 * @var bool
	 */
	public $errorsAtInputs = TRUE;

	/**
	 * Groups that should be rendered first
	 */
	public $priorGroups = array();

	/**
	 * @var \Nette\Forms\Form
	 */
	private $form;

	/**
	 * @var \Nette\Templating\Template|\stdClass
	 */
	private $template;



	/**
	 * @param \Nette\Templating\FileTemplate $template
	 */
	public function __construct(FileTemplate $template = NULL)
	{
		if ($template === NULL) {
			$template = new FileTemplate();
			$template->registerFilter(new \Nette\Latte\Engine());
		}

		$template->setFile(__DIR__ . '/@form.latte');
		$this->template = $template;
	}



	/**
	 * Render the templates
	 *
	 * @param \Nette\Forms\Form $form
	 *
	 * @return void
	 */
	public function render(Nette\Forms\Form $form)
	{
		if ($this->form !== $form) {
			$this->form = $form;

			// translators
			if ($translator = $this->form->getTranslator()) {
				$this->template->setTranslator($translator);
			}

			// controls placeholders & classes
			foreach ($this->form->getControls() as $control) {
				/** @var \Nette\Forms\Controls\BaseControl $control */
				$control->setOption('rendered', FALSE);

				if ($control->isRequired()) {
					$control->getLabelPrototype()
						->addClass('required');
				}

				$el = $control->getControlPrototype();
				if ($el->getName() === 'input') {
					$el->class(strtr($el->type, array(
						'password' => 'text',
						'file' => 'text',
						'submit' => 'button',
						'image' => 'imagebutton',
					)), TRUE);
				}

				if ($placeholder = $control->getOption('placeholder')) {
					if (!$placeholder instanceof Html && $translator) {
						$placeholder = $translator->translate($placeholder);
					}
					$el->placeholder($placeholder);
				}
			}

			$formEl = $form->getElementPrototype();
			if (stripos('form-', $formEl->class) === FALSE) {
				$formEl->addClass('form-horizontal');
			}
		}

		$this->template->form = $this->form;
		$this->template->formErrors = $this->findErrors();
		$this->template->formGroups = $this->findGroups();
		$this->template->formSubmitters = $this->findSubmitters();
		$this->template->renderer = $this;
		$this->template->render();
	}



	/**
	 * @return array
	 */
	private function findErrors()
	{
		if (!$formErrors = $this->form->getErrors()) {
			return array();
		}

		foreach ($this->form->getControls() as $control) {
			/** @var \Nette\Forms\Controls\BaseControl $control */
			if (!$control->hasErrors()) {
				continue;
			}

			$formErrors = array_diff($formErrors, $control->getErrors());
		}

		// If we have translator, translate!
		if ($translator = $this->form->getTranslator()) {
			foreach ($formErrors as $key => $val) {
				$formErrors[$key] = $translator->translate($val);
			}
		}

		return $formErrors;
	}



	/**
	 * @throws \Nette\InvalidStateException
	 * @return object[]
	 */
	private function findGroups()
	{
		$formGroups = $visitedGroups = array();
		foreach ($this->priorGroups as $i => $group) {
			if (!$group instanceof Nette\Forms\ControlGroup) {
				if (!$group = $this->form->getGroup($group)) {
					$groupName = (string)$this->priorGroups[$i];
					throw new Nette\InvalidStateException("Form has no group $groupName.");
				}
			}

			$visitedGroups[] = $group;
			if ($group = $this->buildGroup($group)) {
				$formGroups[] = $group;
			}
		}

		foreach ($this->form->groups as $group) {
			if (!in_array($group, $visitedGroups, TRUE) && ($group = $this->buildGroup($group))) {
				$formGroups[] = $group;
			}
		}

		return $formGroups;
	}



	/**
	 * @return Nette\Forms\ISubmitterControl[]
	 */
	private function findSubmitters()
	{
		$formSubmitters = array();
		foreach ($this->form->getComponents(TRUE, 'Nette\Forms\ISubmitterControl') as $control) {
			if ($control->getOption('rendered')) {
				continue;
			}

			$formSubmitters[] = $control;
		}

		return $formSubmitters;
	}



	/**
	 * @param \Nette\Forms\ControlGroup $group
	 *
	 * @return object
	 */
	protected function buildGroup(Nette\Forms\ControlGroup $group)
	{
		if (!$group->getOption('visual') || !$group->getControls()) {
			return NULL;
		}

		$groupLabel = $group->getOption('label');
		$groupDescription = $group->getOption('description');

		// If we have translator, translate!
		if ($translator = $this->form->getTranslator()) {
			if (!$groupLabel instanceof Html) {
				$groupLabel = $translator->translate($groupLabel);
			}
			if (!$groupDescription instanceof Html) {
				$groupDescription = $translator->translate($groupDescription);
			}
		}

		$groupControls = array();
		foreach ($group->getControls() as $control) {
			/** @var \Nette\Forms\Controls\BaseControl $control */
			if (!$control->getOption('rendered') && !$control instanceof Controls\HiddenField) {
				continue;
			}

			$groupControls[] = $control;
		}

		// fake group
		return (object)array(
			'template' => $group->getOption('template'),
			'controls' => $groupControls,
			'label' => $groupLabel,
			'description' => $groupDescription,
		);
	}



	/**
	 * @internal
	 *
	 * @param \Nette\Forms\Controls\BaseControl $control
	 *
	 * @return string
	 */
	public function getControlName(Controls\BaseControl $control)
	{
		return $control->lookupPath('Nette\Forms\Form');
	}



	/**
	 * @internal
	 *
	 * @param \Nette\Forms\Controls\BaseControl $control
	 *
	 * @return \Nette\Utils\Html
	 */
	public function getControlDescription(Controls\BaseControl $control)
	{
		if (!$desc = $control->getOption('description')) {
			return Html::el();
		}

		// If we have translator, translate!
		if (!$desc instanceof Html && ($translator = $this->form->getTranslator())) {
			$desc = $translator->translate($desc); // wtf?
		}

		// create element
		return Html::el('p', array('class' => 'help-block'))
			->{$desc instanceof Html ? 'add' : 'setText'}($desc);
	}



	/**
	 * @internal
	 *
	 * @param \Nette\Forms\Controls\BaseControl $control
	 *
	 * @return \Nette\Utils\Html
	 */
	public function getControlError(Controls\BaseControl $control)
	{
		if (!$errors = $control->getErrors() || !$this->errorsAtInputs) {
			return Html::el();
		}
		$error = reset($errors);

		// If we have translator, translate!
		if (!$error instanceof Html && ($translator = $this->form->getTranslator())) {
			$error = $translator->translate($error); // wtf?
		}

		// create element
		return Html::el('p', array('class' => 'help-inline'))
			->{$error instanceof Html ? 'add' : 'setText'}($error);
	}



	/**
	 * @internal
	 *
	 * @param \Nette\Forms\Controls\BaseControl $control
	 *
	 * @return string
	 */
	public function getControlTemplate(Controls\BaseControl $control)
	{
		return $control->getOption('template');
	}



	/**
	 * @internal
	 *
	 * @param \Nette\Forms\IControl $control
	 *
	 * @return bool
	 */
	public function isButton(Nette\Forms\IControl $control)
	{
		return $control instanceof Controls\Button;
	}



	/**
	 * @internal
	 *
	 * @param \Nette\Forms\IControl $control
	 *
	 * @return bool
	 */
	public function isCheckbox(Nette\Forms\IControl $control)
	{
		return $control instanceof Controls\Checkbox;
	}



	/**
	 * @internal
	 *
	 * @param \Nette\Forms\IControl $control
	 *
	 * @return bool
	 */
	public function isRadioList(Nette\Forms\IControl $control)
	{
		return $control instanceof Controls\RadioList;
	}



	/**
	 * @internal
	 *
	 * @param \Nette\Forms\Controls\RadioList $control
	 *
	 * @return bool
	 */
	public function getRadioListItems(Controls\RadioList $control)
	{
		$items = array();
		foreach ($control->items as $key => $value) {
			$html = $control->getControl($key);
			$html[1]->addClass('radio');

			$items[$key] = (object)array(
				'input' => $html[0],
				'label' => $html[1],
				'caption' => $html[1]->getText()
			);
		}

		return $items;
	}



	/**
	 * @internal
	 *
	 * @param \Nette\Forms\Controls\BaseControl $control
	 *
	 * @return bool
	 */
	public function isEmail(Controls\BaseControl $control)
	{
		return $control->controlPrototype->type === 'email';
	}

}
