<?php

namespace Drupal\paragraphs_frontend_ui\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Component\Utility\Html;
use Drupal\paragraphs\Entity\Paragraph;

/**
 * Controller for up and down actions.
 */
class ParagraphsFrontendUIController extends ControllerBase {

  /**
   * Shift up a single paragraph.
   */
  public function up($paragraph, $js = 'nojs') {

    extract($this->getParentData($paragraph));

    $paragraph_items = $parent->$parent_field_name->getValue();
    foreach ($paragraph_items as $delta => $paragraph_item) {
      if ($paragraph_item['target_id'] == $paragraph->id()) {
        if ($delta > 0) {
          $prev_paragraph = $paragraph_items[$delta - 1];
          $paragraph_items[$delta - 1] = $paragraph_items[$delta];
          $paragraph_items[$delta] = $prev_paragraph;
        }
        break;
      }
    }
    $parent->$parent_field_name->setValue($paragraph_items);
    $parent->save();

    return $this->refreshWithAJaxResponse($parent, $parent_field_name);
  }

  /**
   * Shift down a single paragraph.
   */
  public function down($paragraph, $js = 'nojs') {
    
    
    extract($this->getParentData($paragraph));

    $paragraph_items = $parent->$parent_field_name->getValue();
    $numitems = count($paragraph_items);
    foreach ($paragraph_items as $delta => $paragraph_item) {
      if ($paragraph_item['target_id'] == $paragraph->id()) {
        if ($delta < $numitems) {
          $next_paragraph = $paragraph_items[$delta + 1];
          $paragraph_items[$delta + 1] = $paragraph_items[$delta];
          $paragraph_items[$delta] = $next_paragraph;
        }
        break;
      }
    }
    $parent->$parent_field_name->setValue($paragraph_items);
    $parent->save();

    return $this->refreshWithAJaxResponse($parent, $parent_field_name);
  }


  /**
   * Duplicate a paragraph.
   */
  public function duplicate($paragraph, $js = 'nojs') {
    
    extract($this->getParentData($paragraph));

    $paragraph_items = $parent->$parent_field_name->getValue();
    $paragraphs_new = [];
    foreach ($paragraph_items as $delta => $paragraph_item) {
      $paragraphs_new[] = $paragraph_item;
      if ($paragraph_item['target_id'] == $paragraph->id()) {

        $cloned_paragraph = $paragraph->createDuplicate();
        $cloned_paragraph->save();
        $paragraphs_new[] = array(
          'target_id' => $cloned_paragraph->id(),
          'target_revision_id' => $cloned_paragraph->getRevisionId(),
        );

      }
    }
    $parent->$parent_field_name->setValue($paragraphs_new);
    $parent->save();

    return $this->refreshWithAJaxResponse($parent, $parent_field_name);
  }

  /**
   * Select a paragraph type.
   */
  public function selectType($paragraph, $js = 'nojs') {

    extract($this->getParentData($paragraph));

    $field_config = \Drupal::entityManager()->getStorage('field_config')->load("$parent_type.$parent_bundle.$parent_field_name");

    // just use the first browser type for now
    $browser_types = \Drupal::entityQuery('paragraphs_browser_type')->execute();
    $browser = paragraphs_browser_type_load(key($browser_types));


    $form = \Drupal::formBuilder()->getForm('Drupal\paragraphs_frontend_ui\Form\ParagraphsFrontendUIBrowserForm', $field_config, $browser, NULL, $paragraph);
    return $form;

  }

  /**
   * Add a form for managing default content for a paragraph.
   */
  public function defaultContent($paragraphs_type) {

    // get the default paragraph, or create a new one
    $type = $paragraphs_type->id();
    if ($paragraph_id = \Drupal::state()->get('paragraphs_frontend_ui_' . $type . '_default')) {
      $paragraph = Paragraph::load($paragraph_id);
    }
    else {
      $paragraph = Paragraph::create([
      'type' => $type,
      ]);
      $paragraph->save();
      \Drupal::state()->set('paragraphs_frontend_ui_' . $type . '_default', $paragraph->id());
    }

    $form = \Drupal::service('entity.form_builder')->getForm($paragraph, 'default');
    return $form;
  }

  /**
   * Helper function to get the required data about the parent of the paragraph
   */
  private function getParentData($paragraph) {
    $parent = $paragraph->getParentEntity();
    return [
      'parent' => $parent,
      'parent_type' => $parent->getEntityTypeId(),
      'parent_bundle' => $parent->getType(),
      'parent_entity_id' => $parent->id(),
      'parent_field_name' => $paragraph->get('parent_field_name')->getValue()[0]['value'],
    ];
  }

  /**
   * Helper function to refresh the field with ajax.
   */
  private function refreshWithAJaxResponse($entity, $field_name) {

    $identifier = '[data-paragraphs-frontend-ui=' . $field_name . '-' . $entity->id() . ']';
    $response = new AjaxResponse();
    // Refresh the paragraphs field.
    $response->addCommand(
      new ReplaceCommand(
        $identifier,
        $entity->get($field_name)->view('default')
      )
    );
    return $response;
  }

}
