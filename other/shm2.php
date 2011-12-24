<?php
/*
$sem_id = sem_get(64);
if($sem_id)
{
    if(sem_acquire($sem_id))
    {

    }
    sem_release($sem_id);
}
*/
$shm_id = shm_attach(1234);
echo "have an id $shm_id";
$hello = shm_get_var($shm_id, 1234);
var_dump($hello);