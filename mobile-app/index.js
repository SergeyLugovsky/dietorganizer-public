/**
 * @format
 */

import { AppRegistry } from 'react-native';
import { getApp } from '@react-native-firebase/app';
import { getMessaging, setBackgroundMessageHandler } from '@react-native-firebase/messaging';
import App from './App';
import { name as appName } from './app.json';

const messagingInstance = getMessaging(getApp());

setBackgroundMessageHandler(messagingInstance, async () => {
  // Background push is handled by the OS notification tray.
});

AppRegistry.registerComponent(appName, () => App);
