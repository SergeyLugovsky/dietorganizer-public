import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

type PushSettingsScreenProps = {
  permissionGranted: boolean;
  hasToken: boolean;
  syncing: boolean;
  error: string | null;
  onEnablePress: () => void;
  onDisablePress: () => void;
  onSyncPress: () => void;
  onContinuePress: () => void;
};

function PushSettingsScreen({
  permissionGranted,
  hasToken,
  syncing,
  error,
  onEnablePress,
  onDisablePress,
  onSyncPress,
  onContinuePress,
}: PushSettingsScreenProps): React.JSX.Element {
  const statusText = hasToken
    ? 'Push is active and synced with server.'
    : permissionGranted
      ? 'Permission is granted, but token is not synced yet.'
      : 'Notification permission is not granted.';

  return (
    <View style={styles.container}>
      <Text style={styles.title}>Notifications</Text>
      <Text style={styles.description}>
        Enable push to receive meal reminders even when the app is closed.
      </Text>
      <View style={styles.card}>
        <Text style={styles.cardLabel}>Status</Text>
        <Text style={styles.cardValue}>{statusText}</Text>
      </View>
      {error ? <Text style={styles.error}>{error}</Text> : null}
      <Pressable
        style={[styles.button, styles.primaryButton, syncing ? styles.buttonDisabled : null]}
        onPress={onEnablePress}
        disabled={syncing}
      >
        <Text style={styles.primaryButtonText}>{syncing ? 'Configuring...' : 'Enable notifications'}</Text>
      </Pressable>
      <Pressable
        style={[styles.button, styles.secondaryButton, syncing ? styles.buttonDisabled : null]}
        onPress={onSyncPress}
        disabled={syncing}
      >
        <Text style={styles.secondaryButtonText}>Retry token sync</Text>
      </Pressable>
      <Pressable
        style={[styles.button, styles.secondaryButton, syncing ? styles.buttonDisabled : null]}
        onPress={onDisablePress}
        disabled={syncing}
      >
        <Text style={styles.secondaryButtonText}>Disable notifications</Text>
      </Pressable>
      <Pressable style={[styles.button, styles.linkButton]} onPress={onContinuePress}>
        <Text style={styles.linkButtonText}>Continue to app</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  container: {
    flex: 1,
    backgroundColor: '#0f1115',
    paddingHorizontal: 20,
    paddingTop: 28,
  },
  title: {
    color: '#ffffff',
    fontSize: 28,
    fontWeight: '700',
    marginBottom: 10,
  },
  description: {
    color: '#c5cad3',
    fontSize: 15,
    lineHeight: 22,
    marginBottom: 20,
  },
  card: {
    backgroundColor: '#151a22',
    borderColor: 'rgba(255,255,255,0.08)',
    borderWidth: 1,
    borderRadius: 12,
    padding: 14,
    marginBottom: 14,
  },
  cardLabel: {
    color: '#8f99a8',
    fontSize: 12,
    textTransform: 'uppercase',
    marginBottom: 8,
    letterSpacing: 0.4,
  },
  cardValue: {
    color: '#f0f3f7',
    fontSize: 15,
    lineHeight: 22,
  },
  error: {
    color: '#ff8b8b',
    marginBottom: 12,
  },
  button: {
    borderRadius: 12,
    paddingVertical: 13,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: 10,
  },
  primaryButton: {
    backgroundColor: '#2f9f57',
  },
  primaryButtonText: {
    color: '#ffffff',
    fontWeight: '700',
    fontSize: 16,
  },
  secondaryButton: {
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.16)',
    backgroundColor: '#151a22',
  },
  secondaryButtonText: {
    color: '#f0f3f7',
    fontSize: 15,
    fontWeight: '600',
  },
  linkButton: {
    backgroundColor: 'transparent',
  },
  linkButtonText: {
    color: '#c5cad3',
    fontSize: 15,
    textDecorationLine: 'underline',
  },
  buttonDisabled: {
    opacity: 0.65,
  },
});

export default PushSettingsScreen;
