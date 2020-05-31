<?php

namespace Drupal\probo\Objects;

class ProboRepository {

  public $id;
  public $name;
  public $projectName;
  public $url;
  public $avatar;

  public function __construct() {
    $this->id = NULL;
    $this->name = NULL;
    $this->projectName = NULL;
    $this->url = NULL;
  }

  public function setId($id) {
    $this->id = $id;
  }

  public function getId() {
    if (!empty($this->id)) {
      return $this->id;
    }
    else {
      return NULL;
    }
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

  public function setProjectName($projectName) {
    $this->projectName = $projectName;
  }

  public function getProjectName() {
    if (!empty($this->projectName)) {
      return $this->projectName;
    }
    else {
      return NULL;
    }
  }

  public function setUrl($url) {
    $this->url = $url;
  }

  public function getUrl() {
    if (!empty($this->url)) {
      return $this->url;
    }
    else {
      return NULL;
    }
  }

  public function setAvatar($url) {
    $this->avatar = $url;
  }

  public function getAvatar() {
    if (!empty($this->avatar)) {
      return $this->avatar;
    }
    else {
      return NULL;
    }
  }

} 