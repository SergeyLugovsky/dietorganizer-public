import AsyncStorage from '@react-native-async-storage/async-storage';
import { getApp } from '@react-native-firebase/app';
import {
  deleteToken,
  getInitialNotification,
  getMessaging,
  getToken,
  onMessage,
  onNotificationOpenedApp,
  onTokenRefresh,
  registerDeviceForRemoteMessages,
  requestPermission,
  unregisterDeviceForRemoteMessages,
  type FirebaseMessagingTypes,
} from '@react-native-firebase/messaging';
import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  PermissionsAndroid,
  Platform,
  StatusBar,
  StyleSheet,
  View,
} from 'react-native';
import { SafeAreaProvider, SafeAreaView } from 'react-native-safe-area-context';
import type { WebView as WebViewType } from 'react-native-webview';
import { APP_VERSION, WEB_APP_URL, WEB_APP_URL_ORIGIN } from './src/config';
import OfflineScreen from './src/screens/OfflineScreen';
import PushSettingsScreen from './src/screens/PushSettingsScreen';
import WebViewScreen from './src/screens/WebViewScreen';

const STORAGE_DEVICE_ID_KEY = 'diet_organizer_device_id';
const messagingInstance = getMessaging(getApp());
type ActiveScreen = 'web' | 'push' | 'offline';

function generateDeviceId(): string {
  return `device_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`;
}

function normalizeUrl(value?: string): string | null {
  if (!value || typeof value !== 'string') {
    return null;
  }
  if (/^https?:\/\//i.test(value)) {
    return value;
  }
  if (value.startsWith('/')) {
    return `${WEB_APP_URL_ORIGIN}${value}`;
  }
  return `${WEB_APP_URL_ORIGIN}/${value}`;
}

function getMessageUrl(message: FirebaseMessagingTypes.RemoteMessage | null): string | null {
  if (!message) {
    return null;
  }
  const dataUrl = message.data?.url;
  if (typeof dataUrl === 'string' && dataUrl.length > 0) {
    return normalizeUrl(dataUrl);
  }
  const androidLink = message.notification?.android?.link;
  return typeof androidLink === 'string' ? normalizeUrl(androidLink) : null;
}

function buildRegisterScript(token: string, deviceId: string): string {
  return `
    (function () {
      if (!window.DietOrganizer || typeof window.DietOrganizer.registerMobilePushToken !== 'function') {
        return;
      }
      window.DietOrganizer.registerMobilePushToken(
        ${JSON.stringify(token)},
        ${JSON.stringify(Platform.OS)},
        ${JSON.stringify(deviceId)},
        ${JSON.stringify(APP_VERSION)}
      ).catch(function () {});
    })();
    true;
  `;
}

function buildUnregisterScript(token: string | null, deviceId: string | null): string {
  return `
    (function () {
      if (!window.DietOrganizer || typeof window.DietOrganizer.unregisterMobilePushToken !== 'function') {
        return;
      }
      window.DietOrganizer.unregisterMobilePushToken(
        ${JSON.stringify(token)},
        ${JSON.stringify(deviceId)},
        ${JSON.stringify(Platform.OS)}
      ).catch(function () {});
    })();
    true;
  `;
}

function App() {
  const webViewRef = useRef<WebViewType | null>(null);

  const [appLoading, setAppLoading] = useState(true);
  const [activeScreen, setActiveScreen] = useState<ActiveScreen>('web');
  const [webReady, setWebReady] = useState(false);
  const [webViewKey, setWebViewKey] = useState(1);
  const [offlineMessage, setOfflineMessage] = useState<string | null>(null);
  const [deviceId, setDeviceId] = useState<string | null>(null);
  const [fcmToken, setFcmToken] = useState<string | null>(null);
  const [pushPermissionGranted, setPushPermissionGranted] = useState(
    Platform.OS !== 'android' || Platform.Version < 33,
  );
  const [pushSyncing, setPushSyncing] = useState(false);
  const [pushError, setPushError] = useState<string | null>(null);
  const [pendingOpenUrl, setPendingOpenUrl] = useState<string | null>(null);

  const registerTokenInWeb = useCallback(() => {
    if (!webReady || !fcmToken || !deviceId || !webViewRef.current) {
      return;
    }
    webViewRef.current.injectJavaScript(buildRegisterScript(fcmToken, deviceId));
  }, [webReady, fcmToken, deviceId]);

  const openUrlInWebView = useCallback((url: string | null) => {
    if (!url) {
      return;
    }
    if (!webReady || !webViewRef.current) {
      setPendingOpenUrl(url);
      setActiveScreen('web');
      return;
    }
    const script = `window.location.href = ${JSON.stringify(url)}; true;`;
    webViewRef.current.injectJavaScript(script);
  }, [webReady]);

  const ensureDeviceId = useCallback(async (): Promise<string> => {
    const saved = await AsyncStorage.getItem(STORAGE_DEVICE_ID_KEY);
    if (saved) {
      return saved;
    }
    const nextId = generateDeviceId();
    await AsyncStorage.setItem(STORAGE_DEVICE_ID_KEY, nextId);
    return nextId;
  }, []);

  const hasNotificationPermission = useCallback(async (): Promise<boolean> => {
    if (Platform.OS !== 'android' || Platform.Version < 33) {
      return true;
    }
    const result = await PermissionsAndroid.check(PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS);
    return result === true;
  }, []);

  const syncPushToken = useCallback(async (askForPermission: boolean): Promise<boolean> => {
    setPushSyncing(true);
    setPushError(null);

    try {
      const ensuredDeviceId = deviceId ?? (await ensureDeviceId());
      if (!deviceId) {
        setDeviceId(ensuredDeviceId);
      }

      let permissionGranted = await hasNotificationPermission();
      if (!permissionGranted && askForPermission && Platform.OS === 'android' && Platform.Version >= 33) {
        const requested = await PermissionsAndroid.request(
          PermissionsAndroid.PERMISSIONS.POST_NOTIFICATIONS,
        );
        permissionGranted = requested === PermissionsAndroid.RESULTS.GRANTED;
      }

      if (!permissionGranted) {
        setPushPermissionGranted(false);
        setPushError('Notification permission is required for push reminders.');
        return false;
      }

      setPushPermissionGranted(true);

      await registerDeviceForRemoteMessages(messagingInstance);
      if (Platform.OS === 'ios') {
        await requestPermission(messagingInstance);
      }
      const token = await getToken(messagingInstance);
      if (!token) {
        setPushError('FCM token is empty. Try again.');
        return false;
      }

      setFcmToken(token);
      return true;
    } catch {
      setPushError('Unable to initialize push notifications.');
      return false;
    } finally {
      setPushSyncing(false);
    }
  }, [deviceId, ensureDeviceId, hasNotificationPermission]);

  const handleWebReady = useCallback(() => {
    setWebReady(true);
    setOfflineMessage(null);
  }, []);

  const handleWebError = useCallback((description?: string) => {
    setWebReady(false);
    setOfflineMessage(description ?? 'WebView load failed.');
    setActiveScreen('offline');
  }, []);

  const handleOfflineRetry = useCallback(() => {
    setOfflineMessage(null);
    setWebReady(false);
    setActiveScreen('web');
    setWebViewKey((prev) => prev + 1);
  }, []);

  const handleEnablePush = useCallback(async () => {
    await syncPushToken(true);
  }, [syncPushToken]);

  const handleRetryPushSync = useCallback(async () => {
    await syncPushToken(true);
  }, [syncPushToken]);

  const handleDisablePush = useCallback(async () => {
    setPushSyncing(true);
    setPushError(null);

    try {
      const tokenToRemove = fcmToken ?? null;
      if (tokenToRemove && webReady && webViewRef.current) {
        webViewRef.current.injectJavaScript(buildUnregisterScript(tokenToRemove, deviceId));
      }

      await deleteToken(messagingInstance);
      if (Platform.OS === 'ios') {
        await unregisterDeviceForRemoteMessages(messagingInstance);
      }

      setFcmToken(null);
      Alert.alert('Notifications disabled', 'Push notifications are disabled on this device.');
    } catch {
      setPushError('Unable to disable notifications.');
    } finally {
      setPushSyncing(false);
    }
  }, [deviceId, fcmToken, webReady]);

  const handleOpenPushSettingsFromWeb = useCallback(() => {
    setActiveScreen('push');
  }, []);

  useEffect(() => {
    let mounted = true;

    const bootstrap = async () => {
      try {
        const id = await ensureDeviceId();
        if (mounted) {
          setDeviceId(id);
        }

        const permissionGranted = await hasNotificationPermission();
        if (mounted) {
          setPushPermissionGranted(permissionGranted);
        }

        if (permissionGranted) {
          await syncPushToken(false);
        }

        if (mounted) {
          setActiveScreen(permissionGranted ? 'web' : 'push');
        }
      } catch {
        if (mounted) {
          setPushError('Unable to initialize push notifications.');
          setActiveScreen('push');
        }
      } finally {
        if (mounted) {
          setAppLoading(false);
        }
      }
    };

    bootstrap();

    const unsubscribeForeground = onMessage(messagingInstance, (message) => {
      const title = message.notification?.title ?? 'Meal reminder';
      const body = message.notification?.body ?? 'You have a new reminder.';
      Alert.alert(title, body, [
        {
          text: 'Open',
          onPress: () => openUrlInWebView(getMessageUrl(message)),
        },
        { text: 'Close', style: 'cancel' },
      ]);
    });

    const unsubscribeOpened = onNotificationOpenedApp(messagingInstance, (message) => {
      openUrlInWebView(getMessageUrl(message));
    });

    getInitialNotification(messagingInstance)
      .then((message) => {
        openUrlInWebView(getMessageUrl(message));
      })
      .catch(() => {});

    const unsubscribeTokenRefresh = onTokenRefresh(messagingInstance, (token) => {
      setFcmToken(token);
    });

    return () => {
      mounted = false;
      unsubscribeForeground();
      unsubscribeOpened();
      unsubscribeTokenRefresh();
    };
  }, [ensureDeviceId, hasNotificationPermission, openUrlInWebView, syncPushToken]);

  useEffect(() => {
    registerTokenInWeb();
  }, [registerTokenInWeb]);

  useEffect(() => {
    if (!pendingOpenUrl || !webReady || !webViewRef.current || activeScreen !== 'web') {
      return;
    }
    const script = `window.location.href = ${JSON.stringify(pendingOpenUrl)}; true;`;
    webViewRef.current.injectJavaScript(script);
    setPendingOpenUrl(null);
  }, [activeScreen, pendingOpenUrl, webReady]);

  if (appLoading) {
    return (
      <SafeAreaProvider>
        <SafeAreaView style={styles.safeArea}>
          <StatusBar barStyle="light-content" />
          <View style={styles.loaderContainer}>
            <ActivityIndicator size="large" color="#2f9f57" />
          </View>
        </SafeAreaView>
      </SafeAreaProvider>
    );
  }

  return (
    <SafeAreaProvider>
      <SafeAreaView style={styles.safeArea}>
        <StatusBar barStyle="light-content" />
        {activeScreen === 'push' ? (
          <PushSettingsScreen
            permissionGranted={pushPermissionGranted}
            hasToken={Boolean(fcmToken)}
            syncing={pushSyncing}
            error={pushError}
            onEnablePress={handleEnablePush}
            onDisablePress={handleDisablePush}
            onSyncPress={handleRetryPushSync}
            onContinuePress={() => {
              setActiveScreen('web');
            }}
          />
        ) : null}
        {activeScreen === 'offline' ? (
          <OfflineScreen message={offlineMessage} onRetryPress={handleOfflineRetry} />
        ) : null}
        {activeScreen === 'web' ? (
          <WebViewScreen
            sourceUrl={WEB_APP_URL}
            webViewRef={webViewRef}
            webViewKey={webViewKey}
            onReady={handleWebReady}
            onLoadError={handleWebError}
            onOpenPushSettings={handleOpenPushSettingsFromWeb}
          />
        ) : null}
      </SafeAreaView>
    </SafeAreaProvider>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#0f1115',
  },
  loaderContainer: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#0f1115',
  },
});

export default App;
