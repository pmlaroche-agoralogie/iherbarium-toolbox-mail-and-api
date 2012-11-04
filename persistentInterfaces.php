<?php
namespace iHerbarium;

interface PersistentUserI {
  public function getUserUid($username);
  public function createUser($username, $password, $lang, $name);
}

interface PersistentObservationI {
  
  public function saveObservation(TypoherbariumObservation $obs, $uid); // = INSERT or UPDATE
  public function loadObservation($obsId);
  public function deleteObservation($obsId);
  
  public function getAllObsIdsForUID($uid);
  
}

interface PersistentPhotoI {
  
  public function addPhotoToObservation(TypoherbariumPhoto $photo, $obsId, $uid);
  //  private function buildPhotoFilename(TypoherbariumPhoto $photo);
  //  private function createPhotoFiles(TypoherbariumPhoto $photo);
  public function loadPhoto($photoId);
  public function deletePhoto($photoId);
  //  private function deletePhotoFiles(TypoherbariumPhoto $photo);
  //  private function deletePhotoSourceFile(TypoherbariumPhoto $photo);
  //  private function deletePhotoVersionsFiles(TypoherbariumPhoto $photo);
  
}

interface PersistentROII {
  
  public function addROIToPhoto($roi, $tagId, $photoId, $uid);
  //  public function buildROIFilename($roiId);
  //  private function createROIFiles($roi, $photo);
  public function loadROI($roiId);
  public function deleteROI($roiId);
  //  public function deleteROIFileVersions($roi);
  public function deleteROIsByPhoto($photoId);
  
}

interface PersistentTagI {
  
  public function addTagToROI(TypoherbariumTag $tag, $roiId);
  public function loadTag($tagId);
  public function deleteTag($tagId);
  
  public function loadTagTranslations();
  
}

interface PersistentAnswerI {
  
  public function addAnswerToROI(TypoherbariumROIAnswer $answer, $roiId);
  public function loadAnswer($answerId);
  public function saveAnswer(TypoherbariumROIAnswer $answer); // = INSERT !
  public function deleteAnswer($answerId);
  
}

interface PersistentAnswersPatternI {
  
  public function loadAnswersPattern($apId);
  public function saveAnswersPattern(TypoherbariumROIAnswersPattern $ap); // = INSERT !
  public function deleteAnswersPattern($apId);
  
}

interface PersistentQuestionI {
  
  public function loadQuestion($qId);
  public function loadQuestionTranslations();
  public function loadQuestionsSchema();
  public function logQuestionAsked(TypoherbariumAskLog $log);
  
}

interface PersistentComparatorI {
  
  public function loadTranslation($translationId);
  public function loadExceptionsHandling($questionId);
  public function loadProximityMatrix($questionId);
  public function loadColorPalette($paletteId);
  public function loadQuestionsOptions();
  public function loadTagsOptions();
  
}

interface PersistentTaskI {
  
  public function addTask(TypoherbariumTask $task);
  public function loadTask($taskId);
  public function loadNextTask();
  public function loadEqualTask(TypoherbariumTask $task);
  public function loadTaskByParams($type, $roiId, $questionId = NULL);
  public function deleteTask($taskId);
  
}

interface PersistentGroupI {

  public function loadGroups();
  
  public function loadGroup($groupId);
  public function createGroup(TypoherbariumGroup $group);
  public function deleteGroup(TypoherbariumGroup $group);
  
  public function addObservationToGroup(TypoherbariumObservation $obs, TypoherbariumGroup $group);
  public function deleteObservationFromGroup(TypoherbariumObservation $obs, TypoherbariumGroup $group);
  
  public function includeGroupInGroup(TypoherbariumGroup $includedGroup, TypoherbariumGroup $group);
  public function excludeGroupFromGroup(TypoherbariumGroup $includedGroup, TypoherbariumGroup $group);
  
  public function loadGroupTranslations();
  
}


?>