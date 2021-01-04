<?php 

namespace Drupal\probo_connector\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class UserSessionObjectSubscriber implements EventSubscriberInterface {

  public function __construct() {
    $user = \Drupal::currentUser();
    $this->accountProxy = $user;
  }

  public function onRequest(GetResponseEvent $event) {
    $user_id = $this->accountProxy->id();
    // Make sure we're logged in before doing anything
    if (!empty($user_id) && $user_id > 0) {
      $service_data = [
        'bitbucket' => FALSE,
        'github' => FALSE,
        'gitlab' => FALSE,
      ];

      $store = \Drupal::service('tempstore.private')->get('probo');
      $services = $store->get('services');
      //if (empty($services)) {
        /** Check for a Bitbucket account linkage */
        $query = \Drupal::database()->select('probo_bitbucket', 'pb');
        $query->fields('pb');
        $query->condition('uid', $user_id, '=');
        $bitbucket = $query->execute()->fetchAllAssoc('uid');
        if (!empty($bitbucket)) {
          $service_data['bitbucket'] = $bitbucket[$user_id];
        }
        $store->set('services', $service_data);
      //}
      //else {
      //  dsm('services');
      //  dsm($services);
      //}
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onRequest', 10];
    return $events;
  }

}
