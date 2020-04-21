<?php

namespace Drupal\entity_sync\Client;

/**
 * Defines the interface for SDK iterators.
 *
 * Iterators facilitates getting results from list endpoints that support
 * pagination.
 */
interface IteratorInterface extends \Iterator {

  /**
   * Sets the current position i.e. page index of the iterator.
   *
   * @param int $pageIndex
   *   The page index to set the current position to.
   */
  public function setKey(int $pageIndex): void;

  /**
   * Gets the total number of pages for the iterator.
   *
   * The total number of pages is generally not known before getting the items
   * for at least one page from the API. In that case, we return null.
   *
   * @return int|null
   *   The total number of pages, or null if it is not known yet.
   */
  public function count(): ?int;

  /**
   * Sets the total number of pages for the iterator.
   *
   * @param int $nbPages
   *   The total number of pages.
   */
  public function setCount(int $nbPages): void;

  /**
   * Gets the items for the requested page.
   *
   * It moves the position to the requested page index and fetches the
   * items.
   *
   * @param int $pageIndex
   *   The index of the page for which to get the results.
   *
   * @return \Iterator
   *   An iterator containing the items.
   *
   * @throws \InvalidArgumentException
   *   If the given page index is an invalid position.
   */
  public function get(int $pageIndex): \Iterator;

  /**
   * Moves the position to the requested page.
   *
   * @param int $pageIndex
   *   The index of the page to move the position to.
   *
   * @throws \InvalidArgumentException
   *   If the given page index is invalid.
   */
  public function move(int $pageIndex): void;

}
