import React, { useCallback, useMemo, useRef, useState } from 'react';
import { ActivityIndicator, Animated, StyleSheet, View } from 'react-native';
import { WebView } from 'react-native-webview';
import type { WebViewMessageEvent } from 'react-native-webview';
import type { WebView as WebViewType } from 'react-native-webview';

type WebViewScreenProps = {
  sourceUrl: string;
  webViewRef: React.RefObject<WebViewType | null>;
  webViewKey: number;
  onReady: () => void;
  onLoadError: (description?: string) => void;
  onOpenPushSettings: () => void;
};

function WebViewScreen({
  sourceUrl,
  webViewRef,
  webViewKey,
  onReady,
  onLoadError,
  onOpenPushSettings,
}: WebViewScreenProps): React.JSX.Element {
  const [showLoader, setShowLoader] = useState(true);
  const loaderOpacity = useRef(new Animated.Value(1)).current;

  const hideLoader = useCallback(() => {
    Animated.timing(loaderOpacity, {
      toValue: 0,
      duration: 180,
      useNativeDriver: true,
    }).start(() => {
      setShowLoader(false);
    });
  }, [loaderOpacity]);

  const loadingView = useMemo(
    () => (
      <Animated.View style={[styles.loaderContainer, { opacity: loaderOpacity }]}>
        <ActivityIndicator size="large" color="#2f9f57" />
      </Animated.View>
    ),
    [loaderOpacity],
  );

  const handleMessage = useCallback(
    (event: WebViewMessageEvent) => {
      const payload = event.nativeEvent.data;
      if (!payload) {
        return;
      }

      if (payload === 'open_push_settings') {
        onOpenPushSettings();
        return;
      }

      try {
        const data = JSON.parse(payload) as { type?: string };
        if (data.type === 'open_push_settings') {
          onOpenPushSettings();
        }
      } catch {
        // Ignore unrelated web messages.
      }
    },
    [onOpenPushSettings],
  );

  return (
    <View style={styles.container}>
      <WebView
        key={webViewKey}
        ref={webViewRef}
        style={styles.webview}
        source={{ uri: sourceUrl }}
        javaScriptEnabled
        domStorageEnabled
        sharedCookiesEnabled
        thirdPartyCookiesEnabled
        setSupportMultipleWindows={false}
        allowsInlineMediaPlayback
        onMessage={handleMessage}
        onLoadEnd={() => {
          if (showLoader) {
            hideLoader();
          }
          onReady();
        }}
        onError={(event) => {
          if (showLoader) {
            hideLoader();
          }
          onLoadError(event.nativeEvent.description);
        }}
        startInLoadingState
        renderLoading={() => loadingView}
      />
      {showLoader ? loadingView : null}
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#000000',
  },
  webview: {
    flex: 1,
    backgroundColor: '#000000',
  },
  loaderContainer: {
    ...StyleSheet.absoluteFillObject,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#000000',
  },
});

export default WebViewScreen;
