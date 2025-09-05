<?php

declare(strict_types=1);

namespace Drupal\eksponent_events\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'tickets' formatter.
 */
#[FieldFormatter(
  id: 'field_tickets',
  label: new TranslatableMarkup('Tickets formatter'),
  field_types: ['integer'],
)]
class TicketsFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode): array {
    $element = [];

    foreach ($items as $delta => $item) {
      $value = (int) $item->value;

      if ($value === 0) {
        $element[$delta] = [
          '#markup' => $this->t('SOLD OUT'),
          '#type' => 'item',
        ];
      }
      elseif ($value <= 10) {
        $element[$delta] = [
          '#markup' => $this->t('@value seats left', ['@value' => $value]),
          '#type' => 'item',
        ];
      }
    }

    return $element;
  }

}
