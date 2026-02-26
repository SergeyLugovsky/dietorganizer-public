import React from 'react';
import { Pressable, StyleSheet, Text, View } from 'react-native';

type OfflineScreenProps = {
  message?: string | null;
  onRetryPress: () => void;
};

function OfflineScreen({ message, onRetryPress }: OfflineScreenProps): React.JSX.Element {
  return (
    <View style={styles.container}>
      <Text style={styles.title}>No connection</Text>
      <Text style={styles.description}>
        We could not load the website inside the app. Check network and try again.
      </Text>
      {message ? <Text style={styles.message}>Details: {message}</Text> : null}
      <Pressable style={styles.button} onPress={onRetryPress}>
        <Text style={styles.buttonText}>Retry</Text>
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
    marginBottom: 16,
  },
  message: {
    color: '#8f99a8',
    marginBottom: 20,
    fontSize: 13,
  },
  button: {
    borderRadius: 12,
    paddingVertical: 13,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: '#2f9f57',
  },
  buttonText: {
    color: '#ffffff',
    fontWeight: '700',
    fontSize: 16,
  },
});

export default OfflineScreen;
