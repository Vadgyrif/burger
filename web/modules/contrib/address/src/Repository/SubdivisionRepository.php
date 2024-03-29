<?php

namespace Drupal\address\Repository;

use CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface;
use CommerceGuys\Addressing\Subdivision\SubdivisionRepository as ExternalSubdivisionRepository;
use Drupal\address\Event\AddressEvents;
use Drupal\address\Event\SubdivisionsEvent;
use Drupal\Core\Cache\CacheBackendInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides subdivisions.
 *
 * Subdivisions are stored on disk in JSON and cached inside Drupal.
 */
class SubdivisionRepository extends ExternalSubdivisionRepository {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Creates a SubdivisionRepository instance.
   *
   * @param \CommerceGuys\Addressing\AddressFormat\AddressFormatRepositoryInterface $address_format_repository
   *   The address format repository.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   */
  public function __construct(AddressFormatRepositoryInterface $address_format_repository, EventDispatcherInterface $event_dispatcher, CacheBackendInterface $cache) {
    parent::__construct($address_format_repository);

    $this->eventDispatcher = $event_dispatcher;
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  protected function loadDefinitions(array $parents): array {
    $group = $this->buildGroup($parents);
    if (isset($this->definitions[$group])) {
      return $this->definitions[$group];
    }

    $this->definitions[$group] = [];
    if (!$this->hasData($parents)) {
      // There are no predefined subdivisions on this level.
      return $this->definitions[$group];
    }
    $cache_key = 'address.subdivisions.' . $group;
    if ($cached = $this->cache->get($cache_key)) {
      $this->definitions[$group] = $cached->data;
    }
    else {
      if ($raw_definition = @file_get_contents($this->definitionPath . $group . '.json')) {
        $this->definitions[$group] = json_decode($raw_definition, TRUE);
      }
      // Allow definitions to be provided/altered by event subscribers.
      $event = new SubdivisionsEvent($parents);
      $event->setDefinitions($this->definitions[$group]);
      $this->eventDispatcher->dispatch($event, AddressEvents::SUBDIVISIONS);
      $this->definitions[$group] = $event->getDefinitions();

      $this->definitions[$group] = $this->processDefinitions($this->definitions[$group]);
      $this->cache->set($cache_key, $this->definitions[$group], CacheBackendInterface::CACHE_PERMANENT, ['subdivisions']);
    }

    return $this->definitions[$group];
  }

}
