<?php
/**
 * This file has been created within Animal Group
 *
 * @copyright Animal Group
 */

use Nette;
use Nette\Forms\Controls;
use Nette\Templating\FileTemplate;



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
class TemplateFormRenderer extends Nette\Object implements Nette\Forms\IFormRenderer 
{

    /** @var string directory where all the templates resides */
    private $directory;

    /** @var bool true, if you want to display the field errors also as form errors */
    public $showFieldErorrsGlobally = FALSE;

    /** @var array buttonstack in order to render buttons after a first field */
    private $buttonStack = array();

    /** @var \Nette\Forms\Form */
    private $form;

    /** @var \Nette\Templating\FileTemplate */
    private $template;


    private function init()
    {
            '.required' => 'required',
            '.text' => 'text',
            '.password' => 'text',
            '.file' => 'text',
            '.submit' => 'button',
            '.image' => 'imagebutton',
            '.button' => 'button',

        // TODO: only for back compatiblity - remove?
        $wrapper = & $this->wrappers['control'];
        foreach ($this->form->getControls() as $control) {
            $control->setOption('rendered', FALSE);

            if ($control->isRequired() && isset($wrapper['.required'])) {
                $control->getLabelPrototype()->class('required', TRUE);
            }

            $el = $control->getControlPrototype();
            if ($el->getName() === 'input' && isset($wrapper['.' . $el->type])) {
                $el->class($wrapper['.' . $el->type], TRUE);
            }
        }
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

        $translator = $form->getTranslator();
        foreach($form->getControls() as $control) {
            $control->setOption('rendered', FALSE);
        }

        /**
         * Step 1: render beginning of the form
         */
        $begin = $this->createTemplate($this->directory . '/formBegin.latte', $form);
        $begin->hiddens = array();
        $begin->method = $form->getMethod();
        $begin->action = $form->getAction();
        if (strcasecmp($form->getMethod(), 'get') === 0) {
            $el = clone $form->getElementPrototype();
            $url = explode('?', (string) $el->action, 2);
            $begin->action = $url[0];
            if (isset($url[1])) {
                foreach (preg_split('#[;&]#', $url[1]) as $param) {
                    $parts = explode('=', $param, 2);
                    $name = urldecode($parts[0]);
                    if (!isset($form[$name])) {
                        $begin->hiddens[$name] = urldecode($parts[1]);
                    }
                }
            }
        }

        // Output begin template
        echo $begin;

        /**
         * Step 2: render form-related errors
         */
        if(count($form->getErrors()) > 0) {
            $errors = $this->createTemplate($this->directory . '/formErrors.latte', $form);
            $errors->errors = $form->getErrors();

            // Remove the field errors from form errors?
            if(!$this->showFieldErorrsGlobally) {
                $fieldErrors = array();
                foreach($form->getControls() as $control) {
                    if(!$control->hasErrors()) {
                        continue;
                    }

                    $errors->errors = array_diff($errors->errors, $control->getErrors());
                }
            }

            // If we have translator, translate!
            if($translator) {
                foreach($errors->errors as $key => $val) {
                    $errors->errors[$key] = $translator->translate($val);
                }
            }

            // and show the error part
            echo $errors;
        }

        /**
         * Step 3: render groups, if we have them
         */
        foreach($form->getGroups() as $group) {
            /** @var $group Nette\Forms\ControlGroup */
            if(!$group->getControls() || !$group->getOption('visual')) {
                continue;
            }

            // The group beggining
            $groupBegin = $this->createTemplate($this->directory . '/groupBegin.latte', $form);
            $groupBegin->label = $group->getOption('label');
            $groupBegin->description = $group->getOption('description');
            if(!empty($translator)) {
                $groupBegin->label = $translator->translate($groupBegin->label);
                $groupBegin->description = $translator->translate($groupBegin->description);
            }
            echo $groupBegin;

            // Render controls
            foreach($group->getControls() as $control) {
                $this->renderControl($control, $form);
            }
            $this->renderButtonStack($form);

            // The group end
            $groupEnd = $this->createTemplate($this->directory . '/groupEnd.latte', $form);
            $groupEnd->label = $group->getOption('label');
            $groupEnd->description = $group->getOption('description');
            if(!empty($translator)) {
                $groupEnd->label = $translator->translate($groupBegin->label);
                $groupEnd->description = $translator->translate($groupBegin->description);
            }
            echo $groupEnd;
        }

        /**
         * Step 4: render rest of the controls
         */
        foreach($form->getControls() as $control) {
            $this->renderControl($control, $form);
        }
        $this->renderButtonStack($form);

        /**
         * Step 5: close the form
         */
        $end = clone $begin;
        $end->setFile($this->directory . '/formEnd.latte');
        echo $end;
    }

    /**
     * Render individual control
     *
     * @param Nette\Forms\Controls\BaseControl $control
     * @param Nette\Forms\Form $form
     * @return void
     */
    protected function renderControl(Controls\BaseControl $control, Nette\Forms\Form $form) {
        // skip?
        if ($control->getOption('rendered') || $control->getForm(FALSE) !== $form) {
            return;
        }

        // Button?
        if($control instanceof Controls\Button) {
            $this->buttonStack[] = $control;
            return;
        }
        $this->renderButtonStack($form);

        // Get control template, render, set rendered
        $ex = explode('\\', get_class($control));
        $template = $this->createTemplate($this->directory . '/controls/' . array_pop($ex) . '.latte', $form);
        $template->field = $control;

        echo $template;
        $control->setOption('rendered', true);
    }

    /**
     * Renders button stack from $this->buttonStack
     * @param Nette\Forms\Form $form
     */
    protected function renderButtonStack(Nette\Forms\Form $form) {
        if(empty($this->buttonStack)) {
            return;
        }

        // Get the button stack & render them
        $template = $this->createTemplate($this->directory . '/buttonStack.latte', $form);
        $template->buttons = $this->buttonStack;
        echo $template;

        // Reset button stack & return
        foreach($this->buttonStack as $control) {
            $control->setOption('rendered', true);
        }
        $this->buttonStack = array();
    }

    /**
     * Create template for given file
     * @param string $template
     * @return
     */
    protected function getTemplate() 
    {
        $template = new FileTemplate(__DIR__ . '/@form.latte');
        $template->registerFilter(new \Nette\Latte\Engine());
        $template->form = $form;
        return $template;
    }

}