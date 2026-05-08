<?php

require_once __DIR__ . '/../model/activity.php';

class ItineraryController {

   public function addActivity($data) {

    $activity = new Activity();

    $activity->trip_id = $data['trip_id'];
    $activity->title = $data['title'];
    $activity->activity_location = $data['location']; // FIX
   
    $activity->type = $data['type'];
    $activity->activity_state = $data['activity_state'];
    $activity->activity_date = $data['activity_date'];
    $activity->activity_time = $data['activity_time'];

    $result = $activity->createActivity();

    if ($result) {
    header("Location: itinerary.php?added=1");
    exit(); 
}

    return $result;
}

public function getActivities($trip_id) {
    $activity = new Activity();
    return $activity->getActivitiesByTrip($trip_id);
}

}
?>