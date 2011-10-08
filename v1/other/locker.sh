#!/bin/sh

# Get the command that should be executed, can receive both one or multiple parameters
COMMAND=$@

# As above but as a 'string' this time around
COMMAND_STRING="$*"

# Where to send the e-mail address
EMAIL_ADDRESS="florinpatan@gmail.com"

# What to write in the e-mail
EMAIL_MESSAGE="Error encountered while trying to run: \"$COMMAND_STRING\" \n Server: `hostname`"

# Get the command hash
COMMAND_HASH=`echo $1 | openssl sha1`

# Get the lock file name
LOCK_FILE="/tmp/locker/"$COMMAND_HASH".lck"

# Check to see if the lock file exists or not
if [ -f $LOCK_FILE ]; then
	# And if does exit send a mail to let us know about it
	#echo $EMAIL_MESSAGE
	echo "Script still running"
	echo $EMAIL_MESSAGE
	#mail -s "[Locker][Error] Same command runnign twice" $EMAIL_ADDRESS < $EMAIL_MESSAGE
else
	# And if it doesn't exists then run the command passed

	# Put the command in the lock file
	echo '$COMMAND_STRING' >> $LOCK_FILE

	# Execute command
	$COMMAND
	ouput=$?
	echo $output
#	sleep 20

	# Remove the lock file
	rm -f $LOCK_FILE
fi

exit 0
