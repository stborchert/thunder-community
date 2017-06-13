<?php

namespace Drupal\thunder_forum_reply\Plugin\views\sort;

use Drupal\views\Plugin\views\sort\Date;

/**
 * Sort handler for the newer of last forum reply / entity updated.
 *
 * @ingroup views_sort_handlers
 *
 * @ViewsSort("thunder_forum_reply_statistics_last_updated")
 */
class StatisticsLastUpdated extends Date {

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->node_table = $this->query->ensureTable('node_field_data', $this->relationship);
    $this->field_alias = $this->query->addOrderBy(NULL, "GREATEST(" . $this->node_table . ".changed, " . $this->tableAlias . ".last_reply_timestamp)", $this->options['order'], $this->tableAlias . '_' . $this->field);
  }

}
