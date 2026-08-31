<?php

namespace SPTK\Examples\Widgets;

use SPTK\Widgets\Button;
use SPTK\Widgets\Checkbox;
use SPTK\Widgets\ColorPicker;
use SPTK\Widgets\ColorSelector;
use SPTK\Widgets\DateSelector;
use SPTK\Widgets\FlowRow;
use SPTK\Widgets\DirectorySelector;
use SPTK\Widgets\FileSelector;
use SPTK\Widgets\Input;
use SPTK\Widgets\Label;
use SPTK\Widgets\ListView;
use SPTK\Widgets\MenuItem;
use SPTK\Widgets\RadioButtons;
use SPTK\Widgets\Selector;
use SPTK\Widgets\TextBlock;
use SPTK\Widgets\TextEditor;

class FormExamples {

  public static function menu(ExampleHost $host): MenuItem {
    $menu = new MenuItem('Forms');
    $input = new MenuItem('Input');
    $input->addItem([
      'label' => 'Default',
      'action' => fn() => self::showInput($host, false),
    ]);
    $input->addItem([
      'label' => 'Password',
      'action' => fn() => self::showInput($host, true),
    ]);
    $menu->addItem($input);
    $menu->addItem([
      'label' => 'Checkbox',
      'action' => function() use ($host): void {
        $panel = $host->panel('checkbox-example', 'Checkbox', 'small');
        $panel->addContent(new Checkbox('checkbox-demo', 'Enable option'));
        $panel->addContent(new Checkbox('checkbox-second', 'Remember choice'));
        $host->showPanel('Forms / Checkbox', $panel);
      },
    ]);
    $menu->addItem([
      'label' => 'Multi Input Panel',
      'action' => fn() => self::showMultiInputPanel($host),
    ]);
    $menu->addItem([
      'label' => 'Read Only Fields',
      'action' => fn() => self::showReadOnlyFields($host),
    ]);
    $menu->addItem([
      'label' => 'ColorPicker',
      'action' => function() use ($host): void {
        $panel = $host->panel('color-picker-example', 'ColorPicker', 'big');
        $panel->addContent(new ColorPicker('color-picker-demo', '#ff5500'), 6);
        $host->showPanel('Forms / ColorPicker', $panel);
      },
    ]);
    $radios = new MenuItem('RadioButtons');
    $radios->addItem([
      'label' => 'Inline',
      'action' => function() use ($host): void {
        $panel = $host->panel('radio-inline-example', 'RadioButtons Inline', 'normal');
        $panel->addContent(new RadioButtons('radio-inline', ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large']));
        $host->showPanel('Forms / RadioButtons / Inline', $panel);
      },
    ]);
    $radios->addItem([
      'label' => 'Vertical',
      'action' => function() use ($host): void {
        $panel = $host->panel('radio-vertical-example', 'RadioButtons Vertical', 'normal');
        $panel->addContent(new RadioButtons('radio-vertical', ['draft' => 'Draft', 'review' => 'Review', 'published' => 'Published'], ['vertical' => true]));
        $host->showPanel('Forms / RadioButtons / Vertical', $panel);
      },
    ]);
    $menu->addItem($radios);
    $selectors = new MenuItem('Selectors');
    $selectors->addItem([
      'label' => 'Enum',
      'action' => fn() => self::showSelector($host),
    ]);
    $selectors->addItem([
      'label' => 'Color',
      'action' => fn() => self::showColorSelector($host),
    ]);
    $selectors->addItem([
      'label' => 'Date',
      'action' => fn() => self::showDateSelector($host),
    ]);
    $selectors->addItem([
      'label' => 'Directory',
      'action' => fn() => self::showDirectorySelector($host),
    ]);
    $selectors->addItem([
      'label' => 'File',
      'action' => fn() => self::showFileSelector($host, false),
    ]);
    $selectors->addItem([
      'label' => 'Multiple Files',
      'action' => fn() => self::showFileSelector($host, true),
    ]);
    $selectors->addItem([
      'label' => 'Create File/Dir',
      'action' => fn() => self::showFileCreateSelector($host),
    ]);
    $menu->addItem($selectors);
    $menu->addItem([
      'label' => 'Button',
      'action' => function() use ($host): void {
        $panel = $host->panel('button-example', 'Button', 'small');
        $panel->addContent(new TextBlock('button-note', 'Buttons handle Enter and Space while focused.'));
        $row = new FlowRow('button-row', 'justify');
        $row->place(new Button('button-ok', 'OK'));
        $row->place(new Button('button-apply', 'Apply'));
        $panel->addContent($row);
        $host->showPanel('Forms / Button', $panel);
      },
    ]);
    return $menu;
  }

  protected static function showInput(ExampleHost $host, bool $password): void {
    $panel = $host->panel($password ? 'password-input-example' : 'input-example', $password ? 'Password Input' : 'Input', 'small');
    $panel->addContent(new Label('input-label', $password ? 'Password:' : 'Name:'));
    $input = new Input('input-demo', $password ? 'secret' : 'Ada Lovelace');
    $input->setPassword($password);
    $panel->addContent($input);
    $host->showPanel($password ? 'Forms / Input / Password' : 'Forms / Input / Default', $panel);
  }

  protected static function showMultiInputPanel(ExampleHost $host): void {
    $panel = $host->panel('multi-input-panel-example', 'Multi Input Panel', 'normal');
    $panel->addContent(new Label('multi-input-name-label', 'Name:'));
    $panel->addContent(new Input('multi-input-name', 'Ada Lovelace'));
    $panel->addContent(new Label('multi-input-email-label', 'Email:'));
    $panel->addContent(new Input('multi-input-email', 'ada@example.test'));
    $panel->addContent(new Label('multi-input-options-label', 'Options:'));

    $enabled = new Checkbox('multi-input-enabled', 'Enabled');
    $enabled->setValue(true);
    $panel->addContent($enabled);
    $panel->addContent(new Checkbox('multi-input-notify', 'Send notifications'));
    $panel->addContent(new Checkbox('multi-input-archive', 'Archive completed items'));

    $panel->addContent(new Label('multi-input-list-label', 'Items:'));
    $panel->addContent(new ListView('multi-input-list', [
      ['text' => 'Design review', 'right' => 'today'],
      ['text' => 'Prototype', 'right' => 'ready'],
      ['text' => 'Implementation', 'right' => 'active'],
      ['text' => 'Testing', 'right' => 'queued'],
      ['text' => 'Release notes', 'right' => 'draft'],
    ]), 5);
    $host->showPanel('Forms / Multi Input Panel', $panel);
  }

  protected static function showReadOnlyFields(ExampleHost $host): void {
    $panel = $host->panel('read-only-fields-example', 'Read Only Fields', 'normal');
    $panel->addContent(new Label('read-only-input-label', 'Token:'));
    $input = new Input('read-only-input', 'SPTK-2026-08');
    $input->setReadOnly(true);
    $panel->addContent($input);

    $panel->addContent(new Label('read-only-editor-label', 'Notes:'));
    $editor = new TextEditor('read-only-editor', "Generated configuration\nReview only");
    $editor->setReadOnly(true);
    $panel->addContent($editor, 4);
    $host->showPanel('Forms / Read Only Fields', $panel);
  }

  protected static function showSelector(ExampleHost $host): void {
    $panel = $host->panel('selector-example', 'Selector', 'small');
    $panel->addContent(new Label('selector-label', 'Priority:'));
    $panel->addContent(new Selector('selector-demo', [
      'low' => 'Low',
      'normal' => 'Normal',
      'high' => 'High',
      'urgent' => 'Urgent',
    ], 'normal', [
      'title' => 'Select Priority',
    ]));
    $host->showPanel('Forms / Selectors / Enum', $panel);
  }

  protected static function showDirectorySelector(ExampleHost $host): void {
    $panel = $host->panel('directory-selector-example', 'DirectorySelector', 'normal');
    $panel->addContent(new Label('directory-selector-label', 'Directory:'));
    $panel->addContent(new DirectorySelector('directory-selector-demo', getcwd()));
    $host->showPanel('Forms / Selectors / Directory', $panel);
  }

  protected static function showColorSelector(ExampleHost $host): void {
    $panel = $host->panel('color-selector-example', 'ColorSelector', 'normal');
    $panel->addContent(new Label('color-selector-label', 'Color:'));
    $panel->addContent(new ColorSelector('color-selector-demo', '#ff5500', [
      'title' => 'Select Color',
    ]));
    $host->showPanel('Forms / Selectors / Color', $panel);
  }

  protected static function showDateSelector(ExampleHost $host): void {
    $panel = $host->panel('date-selector-example', 'DateSelector', 'normal');
    $panel->addContent(new Label('date-selector-label', 'Date:'));
    $panel->addContent(new DateSelector('date-selector-demo', date('Y-m-d'), [
      'title' => 'Select Date',
    ]));
    $host->showPanel('Forms / Selectors / Date', $panel);
  }

  protected static function showFileSelector(ExampleHost $host, bool $multiple): void {
    $panel = $host->panel($multiple ? 'multi-file-selector-example' : 'file-selector-example', $multiple ? 'FileSelector Multiple' : 'FileSelector', 'normal');
    $panel->addContent(new Label('file-selector-label', $multiple ? 'PHP files:' : 'PHP file:'));
    $panel->addContent(new FileSelector($multiple ? 'multi-file-selector-demo' : 'file-selector-demo', getcwd(), null, [
      'extensions' => ['php'],
      'multiple' => $multiple,
      'title' => $multiple ? 'Select PHP Files' : 'Select PHP File',
    ]));
    $host->showPanel($multiple ? 'Forms / Selectors / Multiple Files' : 'Forms / Selectors / File', $panel);
  }

  protected static function showFileCreateSelector(ExampleHost $host): void {
    $panel = $host->panel('file-create-selector-example', 'FileSelector Create', 'normal');
    $panel->addContent(new Label('file-create-selector-label', 'File:'));
    $panel->addContent(new FileSelector('file-create-selector-demo', sys_get_temp_dir(), null, [
      'createDirectory' => true,
      'createFile' => true,
      'title' => 'Select or Create File',
    ]));
    $host->showPanel('Forms / Selectors / Create File/Dir', $panel);
  }

}
