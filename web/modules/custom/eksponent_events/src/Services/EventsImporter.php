<?php

namespace Drupal\eksponent_events\Services;

use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use GuzzleHttp\ClientInterface;
use Drupal\node\Entity\Node;
use Drupal\file\FileRepositoryInterface;
use GuzzleHttp\Exception\GuzzleException;
use function Safe\file_get_contents;
use function Safe\parse_url;

/**
 * Service to import events from external API.
 */
class EventsImporter {

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The file repository service.
   *
   * @var \Drupal\Core\File\FileRepositoryInterface
   */
  protected FileRepositoryInterface $fileRepository;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  public function __construct(
    ClientInterface $httpClient,
    FileRepositoryInterface $fileRepository,
    FileSystemInterface $fileSystem,
  ) {
    $this->httpClient = $httpClient;
    $this->fileRepository = $fileRepository;
    $this->fileSystem = $fileSystem;
  }

  /**
   * Import events from the given API URL.
   *
   * @param string $apiUrl
   *   The API URL to fetch events from.
   */
  public function importEvents(string $apiUrl): void {
    try {
      $response = $this->httpClient->request('GET', $apiUrl);
      if ($response->getStatusCode() === 200) {
        $events = json_decode($response->getBody()->getContents(), TRUE);
        foreach ($events as $eventData) {
          $this->createOrUpdateEvent($eventData);
        }
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('eksponent_events')->error('Failed to import events: @message', ['@message' => $e->getMessage()]);
    }
    catch (GuzzleException $e) {
    }
  }

  /**
   * Create or update event node.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createOrUpdateEvent(array $eventData): void {
    $existingNodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['uuid' => $eventData['id']]);

    $node = reset($existingNodes) ?: Node::create(['type' => 'event']);
    $node->set('uuid', $eventData['id']);
    $node->set('title', $eventData['title']);
    $node->set('field_description', $eventData['description']);
    $node->set('field_price', $eventData['price']['amount']);
    $node->set('field_tickets', $eventData['available_tickets']);
    $node->set('field_organizer', $eventData['organizer']['name']);

    // Fetch start and end dates.
    $field_duration = [];
    if (!empty($eventData['start_date'])) {
      $field_duration['value'] = date('Y-m-d\TH:i:s', strtotime($eventData['start_date']));
    }
    if (!empty($eventData['end_date'])) {
      $field_duration['end_value'] = date('Y-m-d\TH:i:s', strtotime($eventData['end_date']));
    }
    $node->set('field_duration', $field_duration);

    // Handle image as media.
    if (!empty($eventData['image'])) {
      $mediaId = $this->importImageAsMedia($eventData['image'], $eventData['title']);
      if ($mediaId) {
        $node->set('field_primary_image', ['target_id' => $mediaId]);
      }
    }

    $node->save();
  }

  /**
   * Import image and create a media entity.
   */
  protected function importImageAsMedia(string $imageUrl, string $title): ?int {
    try {
      $extension = pathinfo(parse_url($imageUrl, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'svg';
      $sanitizedTitle = preg_replace('/[^A-Za-z0-9_\-]/', '_', $title);
      $parsedUrl = $sanitizedTitle . '_' . md5($imageUrl) . '.' . $extension;

      if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        throw new \Exception("Invalid file URL: $imageUrl");
      }

      // Prepare the destination directory.
      $directory = 'public://imported-event-images/';

      if (!$this->fileSystem->prepareDirectory($directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS)) {
        throw new \Exception("Failed to create directory: $directory");
      }

      // Determine the destination path.
      $fileName = basename($parsedUrl);
      $destination = $directory . $fileName;

      // Download the file data.
      $data = file_get_contents($imageUrl);
      if (empty($data)) {
        throw new \Exception('Failed to download file.');
      }

      // Save the file using the file.repository service.
      // If the file name already exists, we replace it.
      // This is to avoid the disk getting filled up with copies
      // of the same file.
      $file = $this->fileRepository->writeData($data, $destination, FileExists::Replace);

      // Check if a media entity already exists for this file.
      $mediaStorage = \Drupal::entityTypeManager()->getStorage('media');
      $media = $mediaStorage->loadByProperties(['field_media_image' => $file->id()]);
      $media = reset($media);

      if (!$media) {
        $media = $mediaStorage->create([
          'bundle' => 'image',
          'name' => $title,
          'field_media_image' => [
            [
              'target_id' => $file->id(),
              'alt' => $title,
            ],
          ],
          'status' => 1,
        ]);
        $media->save();
      }
      return $media->id();
    }
    catch (\Exception $e) {
      \Drupal::logger('eksponent_events')->error('Image import failed: @message', ['@message' => $e->getMessage()]);
      return NULL;
    }

  }

}
