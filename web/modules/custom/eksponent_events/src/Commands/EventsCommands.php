<?php

namespace Drupal\eksponent_events\Commands;

use Drush\Commands\DrushCommands;
use Drupal\eksponent_events\Services\EventsImporter;

/**
 * Drush commands for Eksponent Events module.
 */
class EventsCommands extends DrushCommands {

  /**
   * The events importer service.
   *
   * @var \Drupal\eksponent_events\Services\EventsImporter
   */
  protected EventsImporter $importer;

  public function __construct(EventsImporter $importer) {
    parent::__construct();
    $this->importer = $importer;
  }

  /**
   * Import events from remote API.
   *
   * @command eksponent:events:import
   * @usage eksponent:events:import Imports events from remote API.
   */
  public function import(): void {
    $this->importer->importEvents('https://toolbox.eksponent.com:8030/events.json');
    $this->output()->writeln('Events imported.');
  }

}
