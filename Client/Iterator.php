<?php

namespace Drupal\entity_sync\Client;

/**
 * Default implementation of the client iterator.
 */
class Iterator implements IteratorInterface {

  /**
   * The client that will be used to make requests to the API.
   *
   * @var \Drupal\entity_sync\Client\ClientInterface
   */
  protected $client;

  /**
   * Holds the items fetched from the API, indexed by page index.
   *
   * @var \Iterator
   */
  protected $pages = [];

  /**
   * The total number of pages.
   *
   * @var int
   */
  protected $count;

  /**
   * The current iterator position i.e. the current page index.
   *
   * @var int
   */
  protected $position = 1;

  /**
   * The number of items to fetch per page.
   *
   * @var int
   */
  protected $limit = 100;

  /**
   * Constructs a new Iterator object.
   *
   * @param \Drupal\entity_sync\Client\ClientInterface $client
   *   The client that will be used to make requests to the API.
   * @param int $pageIndex
   *   The index of the page to start the iterator at.
   * @param int $limit
   *   The number of items to fetch per page.
   */
  public function __construct(
    ClientInterface $client,
    int $pageIndex = NULL,
    int $limit = NULL
  ) {
    $this->client = $client;

    if ($pageIndex) {
      $this->position = $pageIndex;
    }
    if ($limit) {
      $this->limit = $limit;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function current(): \Iterator {
    list(
      $this->pages[$this->position],
      $this->count
      ) = $this->client->list(
      [
        'bypass_iterator' => TRUE,
        'page' => $this->position,
        'limit' => $this->limit,
      ]
    );

    // The total number of pages is returned as a string from the API. We
    // store it here as an integer.
    $this->count = (int) $this->count;

    $this->pages[$this->position]->rewind();
    return $this->pages[$this->position];
  }

  /**
   * {@inheritdoc}
   */
  public function key(): int {
    return $this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function next(): void {
    ++$this->position;
  }

  /**
   * {@inheritdoc}
   */
  public function rewind(): void {
    $this->position = 1;
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    if ($this->position < 1) {
      return FALSE;
    }

    // We only know the total number of pages after the first request.
    if ($this->count && $this->position > $this->count) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function setKey(int $pageIndex): void {
    $this->position = $pageIndex;
  }

  /**
   * {@inheritdoc}
   */
  public function count(): ?int {
    if ($this->count) {
      return $this->count;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function setCount(int $nbPages): void {
    $this->count = $nbPages;
  }

  /**
   * {@inheritdoc}
   */
  public function get(int $pageIndex): \Iterator {
    $this->move($pageIndex);
    return $this->current();
  }

  /**
   * {@inheritdoc}
   */
  public function move(int $pageIndex): void {
    $this->position = $pageIndex;

    if (!$this->valid()) {
      throw new \InvalidArgumentException(
        sprintf(
          'Page "%s" is either an invalid page index or it exceeds the total number of pages for the request.',
          $pageIndex
        )
      );
    }
  }

}
