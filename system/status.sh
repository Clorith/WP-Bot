#!/bin/bash
# Script for detecting if our PHP bot is running, or rebooting it if it isn't

PHP_PROCESSES="$(ps -C php --no-headers | wc -l)"
WPBOT_SCREEN="$(screen -ls | grep -q WPBot)"

if [ "$PHP_PROCESSES" == 0 ]
then
  echo "WPBot is not running, instantiating..."

  # If no screen session exists, start one
  if [ ! "$WPBOT_SCREEN" ]
  then
    screen -mdS WPBot
  fi

  # Create a WPBot instance
  screen -S WPBot -p 0 -X stuff "/usr/bin/php /home/contribot/IRC/contributor-bot.php$(printf \\r)"
fi
