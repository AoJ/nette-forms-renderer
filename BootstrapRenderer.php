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
 * You can customize the directory, where all the templates resides.
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
     * @var \Nette\Templating\Template 
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
     * @param Nette\Forms\Form $form
     * @return void
     */
    public function render(Nette\Forms\Form $form) 
    {
        if ($this->form !== $form) {
            $this->form = $form;
            $this->init();
        }

        $this->template->formErrors = $this->findErrors();
        $this->template->formGroups = $this->findGroups();
        $this->template->formSubmitters = $this->findSubmitters();
        $this->template->render();
    }



    /**
     * @return array
     */
    private function findErrors()
    {
        if (!$formErrors = $form->errors) {
            return array();
        }

        foreach($form->getControls() as $control) {
            if(!$control->hasErrors()) {
                continue;
            }

            $formErrors = array_diff($formErrors, $control->getErrors());
        }

        // If we have translator, translate!
        if ($translator = $form->getTranslator()) {
            foreach($formErrors as $key => $val) {
                $formErrors[$key] = $translator->translate($val);
            }
        }

        return $formErrors;
    }



    /**
     * @return object[]
     */
    private function findGroups()
    {
        $formGroups = $visitedGroups = array();
        foreach ($this->priorGroups as $key => $group) {
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

        foreach ($this->form->groups as $key => $group) {
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
     */
    private function init()
    {
        $template = $this->template;
        $template->form = $this->form;
        $template->renderer = $this;
   
        // translators
        if ($translator = $this->form->getTranslator()) {
            $template->setTranslator($translator);
        }

        // type classes
        foreach ($this->form->getControls() as $control) {
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
        }

        $formEl = $form->getElementPrototype();
        if (stripos('form-', $formEl->class) === FALSE) {
            $formEl->addClass('form-horizontal');
        }
    }



    /**
     * @return object
     */
    protected function buildGroup(Nette\Forms\ControlGroup $group)
    {
        if(!$group->getOption('visual') || !$group->getControls()) {
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
            if (!$this->isRenderableInBody($control)) {
                continue;
            }

            $groupControls[] = $control;
        }

        // fake group
        return (object)array(
            'template' => $group->getOption('template'),
            'controls' => $groupControls,
            'label' => $groupLabel,
            'description' => $groupDescription,
        );
    }



    /**
     * @internal
     * @return bool
     */
    public function isRenderableInBody(Nette\Forms\IControl $control)
    {
        return !$control->getOption('rendered')
            && !$control instanceof Nette\Forms\ISubmitterControl 
            && !$control instanceof Controls\HiddenField;
    }



    /**
     * @internal
     * @return string
     */
    public function getControlName(Nette\Forms\IControl $control)
    {
        return $control->lookupPath('Nette\Forms\Form');
    }



    /**
     * @internal
     * @return \Nette\Utils\Html
     */
    public function getControlDescription(Nette\Forms\IControl $control)
    {
        // <p class="help-block" n:if="$desc = $control->getOption('description')">{$desc}</p>
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
     * @return \Nette\Utils\Html
     */
    public function getControlError(Nette\Forms\IControl $control)
    {
        if (!$errors = $control->errors || !$this->errorsAtInputs) {
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
     * @return string
     */
    public function getControlTemplate(Nette\Forms\IControl $control)
    {
        return $control->getOption('template');
    }



    /**
     * @internal
     * @return bool
     */
    public function isButton(Nette\Forms\IControl $control)
    {
        return $control instanceof Controls\Button;
    }



    /**
     * @internal
     * @return bool
     */
    public function isCheckbox(Nette\Forms\IControl $control)
    {
        return $control instanceof Controls\Checkbox;
    }



    /**
     * @internal
     * @return bool
     */
    public function isRadioList(Nette\Forms\IControl $control)
    {
        return $control instanceof Controls\RadioList;
    }



    /**
     * @internal
     * @return bool
     */
    public function getRadioListItems(Controls\RadioList $control)
    {
        $items = array();
        foreach ($control->items as $key => $value) {
            $html = $control->getControl($key);
            $items[$key] = array(
                'input' => $html[0],
                'label' => $html[1]->addClass('radio'),
                'caption' => $html[1]->getText()
            );
        }

        return $items;
    }



    /**
     * @internal
     * @return bool
     */
    public function isEmail(Nette\Forms\IControl $control)
    {
        return $control->controlPrototype->type === 'email';
    }

}