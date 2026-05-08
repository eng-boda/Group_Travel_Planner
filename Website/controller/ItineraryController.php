<?php

require_once __DIR__ . '/../model/activity.php';

class ItineraryController {

   public function checkBufferConflict($trip_id, $date, $new_time, $exclude_id = null) {
        $activityModel = new Activity();
        $activities = $activityModel->getActivitiesByTrip($trip_id);
        
        $new_date_clean = date('Y-m-d', strtotime($date));
        $new_timestamp = strtotime($new_time);

        foreach ($activities as $act) {
            if ($exclude_id && $act['activity_id'] == $exclude_id) continue;

            $existing_date_clean = date('Y-m-d', strtotime($act['activity_date']));

            if ($existing_date_clean == $new_date_clean) {
                $existing_timestamp = strtotime($act['activity_time']);
                $diff_minutes = abs($new_timestamp - $existing_timestamp) / 60;

                if ($diff_minutes < 90) {
                    return [
                        'has_conflict' => true,
                        'existing_title' => $act['title'],
                        'diff' => $diff_minutes
                    ];
                }
            }
        }
        return ['has_conflict' => false];
    }

    public function addActivity($data) {
        if (!isset($data['ignore_conflict'])) {
            $conflict = $this->checkBufferConflict($data['trip_id'], $data['activity_date'], $data['activity_time']);
            if ($conflict['has_conflict']) {
                return $conflict;
            }
        }

        $activity = new Activity();
        $activity->trip_id = $data['trip_id'];
        $activity->title = $data['title'];
        $activity->activity_location = $data['location'];
        $activity->type = $data['type'];
        $activity->activity_state = $data['activity_state'];
        $activity->activity_date = $data['activity_date'];
        $activity->activity_time = $data['activity_time'];

        $result = $activity->createActivity();
        if ($result) {
            header("Location: itinerary.php?trip_id=" . $data['trip_id'] . "&added=1");
            exit(); 
        }
        return $result;
    }

    public function updateActivity($data) {
        if (!isset($data['ignore_conflict'])) {
            $conflict = $this->checkBufferConflict($data['trip_id'], $data['activity_date'], $data['activity_time'], $data['activity_id']);
            if ($conflict['has_conflict']) {
                return $conflict;
            }
        }

        $activity = new Activity();
        $activity->activity_id = $data['activity_id']; 
        $activity->trip_id = $data['trip_id'];
        $activity->title = $data['title'];
        $activity->activity_location = $data['location'];
        $activity->type = $data['type'];
        $activity->activity_state = $data['activity_state'];
        $activity->activity_date = $data['activity_date'];
        $activity->activity_time = $data['activity_time'];

        $result = $activity->update();
        if ($result) {
            header("Location: itinerary.php?trip_id=" . $data['trip_id'] . "&updated=1");
            exit();
        }
        return $result;
    }

public function getActivityById($id) {
    $activity = new Activity();
    return $activity->getActivity($id);
}

// public function updateActivity($data) {
//     $activity = new Activity();
    
//     $activity->activity_id = $data['activity_id']; 
//     $activity->trip_id = $data['trip_id'];
//     $activity->title = $data['title'];
//     $activity->activity_location = $data['location'];
//     $activity->type = $data['type'];
//     $activity->activity_state = $data['activity_state'];
//     $activity->activity_date = $data['activity_date'];
//     $activity->activity_time = $data['activity_time'];

//     $result = $activity->update();
    
//     if ($result) {
//         header("Location: itinerary.php?trip_id=" . $data['trip_id'] . "&updated=1");
//         exit();
//     }
//     return $result;
// }

public function deleteActivity($id, $trip_id) {
    $activity = new Activity();
    $result = $activity->delete($id);

    if ($result) {
        header("Location: itinerary.php?trip_id=" . $trip_id . "&deleted=1");
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