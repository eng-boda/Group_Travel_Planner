<?php

require_once __DIR__ . '/../model/item.php';

class ChecklistController {

    public function add($data) {

        $item = new item();

        $item->trip_id = $data['trip_id'];
        $item->user_id = $data['user_id'];
        $item->itemName = $data['itemName'];

        return $item->addItem();
    }

    public function getAll($trip_id) {

        $item = new item();

        return $item->getItems($trip_id);
    }

    public function done($item_id) {

        $item = new item();

        return $item->markDone($item_id);
    }
 public function toggle($item_id, $current_status, $userId) {
    
    $itemModel = new item();
    return $itemModel->toggleStatus($item_id, $current_status, $userId);
}
public function delete($item_id) {
    $itemModel = new item();
    return $itemModel->deleteItem($item_id);
}
}
?>