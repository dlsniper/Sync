<?php

$sem_id = sem_get(64);
if ($sem_id) {
    if (sem_acquire($sem_id)) {
        $shm_id = shm_attach(1234);
        shm_remove_var($shm_id, 1234);
        shm_remove($shm_id);
        shm_detach($shm_id);
    }
    sem_release($sem_id);
}

