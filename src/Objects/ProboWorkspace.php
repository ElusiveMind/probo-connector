<?php

namespace Drupal\probo_connector\Objects;

use Drupal\probo_connector\Objects\ProboRepository;

class ProboWorkspace {

  public $name = NULL;
  public $machineName = NULL;
  public $repositories = NULL;
  public $url = NULL;
  
  public function __construct() {
    $this->name = NULL;
    $this->machine_name = NULL;
    $this->repositories = [];
    $this->url = NULL;
  }

  public function setName($name) {
    $this->name = $name;
  }

  public function getName() {
    if (!empty($this->name)) {
      return $this->name;
    }
    else {
      return NULL;
    }
  }

  public function setMachineName($machineName) {
    $this->machineName = $machineName;
  }

  public function getMachineName() {
    if (!empty($this->machineName)) {
      return $this->machineName;
    }
    else {
      return NULL;
    }
  }

  public function addRepository(ProboRepository $repository) {
    $this->repositories[] = $repository;
  }

  public function getRepositories() {
    if (!empty($this->repositories)) {
      return $this->repositories;
    }
    else {
      return [];
    }
  }

  public function setUrl($url) {
    $this->Url = $url;
  }

  public function getUrl() {
    if (!empty($this->Url)) {
      return $this->Url;
    }
    else {
      return NULL;
    }
  }

} 