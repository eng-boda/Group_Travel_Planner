<?php

class Document {
    public int    $doc_id;
    public int    $trip_id;
    public int    $user_id;
    public ?int   $activity_id = null;
    public string $file_name;
    public string $stored_name;
    public string $file_type;
    public int    $file_size;
    public string $category    = 'general';
    public string $doc_state   = 'active';
    public string $uploaded_at;
}
?>