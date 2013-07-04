#!/bin/bash

# script to exceute a specific command only at one time
# if there is another command running at the same time, script will send an email

### CONFIG ###

EMAIL_ADDRESS="florinpatan@gmail.com"

### MAIN ###

while getopts "ht:d" opt; do
  case $opt in
    h)
      echo "script to run a command exclusively"
      echo "usage: $0 [-t <minutes>] [-h] <command>"
      echo ""
      echo "params:"
      echo "-t <minutes>	mail only if command runs longer than <minutes>"
      echo "-h		display this usage info"
      echo "<command>	command to execute"
      echo ""
      exit 0
      ;;
    t)
      TIME="$OPTARG"
      ;;
    \?)
      echo "invalid option -$OPTARG" >&2
      exit 1
      ;;
  esac
done

# shift all the known options away
shift $((OPTIND-1))
  
# Get the command that should be executed, can receive both one or multiple parameters
COMMAND=$@

# As above but as a 'string' this time around
COMMAND_STRING="$*"

# Get the command hash
COMMAND_HASH=`echo "$COMMAND_STRING" | openssl sha1 | cut -d " " -f 2`

# error message which to send in the email
EMAIL_MESSAGE="/tmp/$COMMAND_HASH.err"

echo "Error encountered while trying to run: \"$COMMAND_STRING\"" > $EMAIL_MESSAGE 
echo "Server: `hostname`" >> $EMAIL_MESSAGE

# Get the lock file name
LOCK_FILE="/tmp/"$COMMAND_HASH".lck"

# Check to see if the lock file exists or not
if [ -f $LOCK_FILE ]; then
  # And if it does exist send a mail to let us know about it

  # check if time parameter is given and send only mail if we run longer
  if [ -n "$TIME" ]; then
    current_time="$(date +%s)"
    end_time="$(grep "end-time:" $LOCK_FILE | cut -d ":" -f2)"

    if [ -z "$end_time" -o $current_time -gt $end_time ]; then
        cat $EMAIL_MESSAGE | mail -s "[Locker][Error] Same command running twice" $EMAIL_ADDRESS
        EC=254
    else
        # set other custome exit code
        EC=125
    fi
  else
    # no time param given...send an email
    cat $EMAIL_MESSAGE | mail -s "[Locker][Error] Same command running twice" $EMAIL_ADDRESS
    EC=254
  fi
else
  # And if it doesn't exist then run the command passed

  # Put the command and the enddate in the lock file
  echo "end-time:$(date -d "+$TIME minutes" +%s)" >> $LOCK_FILE
  echo "$COMMAND_STRING" >> $LOCK_FILE

  # Execute command
  $COMMAND

  # Get the exit code of the command
  EXIT_CODE=$?

  # Check to see if the exit code is different than 0
  if [[ $EXIT_CODE != 0 ]] ; then
    # Add the exit code to the email
    echo "Exit code $EXIT_CODE" >> $EMAIL_MESSAGE

    # Send the email
    cat $EMAIL_MESSAGE | mail -s "[Locker][Error] Bad exit code from command" $EMAIL_ADDRESS

    # Remove the lock and e-mail file and exit witht the command exit code
    rm -f $LOCK_FILE
    rm -f $EMAIL_MESSAGE
    exit $EXIT_CODE
  fi

  # Remove the lock file and set our exit code
  rm -f $LOCK_FILE
  EC=0
fi

# Remove the email message
rm -f $EMAIL_MESSAGE

# Bye now
exit $EC
