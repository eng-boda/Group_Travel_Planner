<?php

require_once __DIR__ . '/../model/noncash.php';

class NonCashController
{
    public function addNonCash($data)
    {
        $nonCash = new NonCash();

        $nonCash->trip_id = $data['trip_id'];
        $nonCash->contributor_id = $user_id;
        $nonCash->estimatedValue = $data['estimatedValue'];
        $nonCash->description = $data['description'];

        // default values
        $nonCash->leader_comment = "";
        $nonCash->status = "Pending";

        // file upload
        $proofFileName = "";

        if (isset($file) && $file['error'] == 0) {

            $uploadDir = __DIR__ . '/../uploads/noncash/';

            // create folder if not exists
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $proofFileName = time() . "_" . basename($file['name']);

            $targetPath = $uploadDir . $proofFileName;

            move_uploaded_file($file['tmp_name'], $targetPath);
        }

        $nonCash->proof_file = $proofFileName;

        return $nonCash->createNonCash();
    }

    public function getNonCash($trip_id)
    {
        $nonCash = new NonCash();

        return $nonCash->getNonCashByTrip($trip_id);
    }
}