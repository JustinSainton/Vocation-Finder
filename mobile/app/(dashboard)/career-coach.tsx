import React, { useState, useRef, useCallback } from 'react';
import {
  View,
  StyleSheet,
  FlatList,
  TextInput,
  Pressable,
  KeyboardAvoidingView,
  Platform,
  ActivityIndicator,
} from 'react-native';
import { SafeAreaView } from 'react-native-safe-area-context';
import { useRouter } from 'expo-router';
import { Typography } from '../../components/ui/Typography';
import { useFeatureFlags } from '../../hooks/useFeatureFlags';
import { api } from '../../services/api';
import { spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

interface Message {
  id: string;
  role: 'user' | 'assistant';
  content: string;
}

export default function CareerCoachScreen() {
  const { colors } = useTheme();
  const { isEnabled } = useFeatureFlags();
  const router = useRouter();
  const [messages, setMessages] = useState<Message[]>([]);
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [conversationId, setConversationId] = useState<string | null>(null);
  const [started, setStarted] = useState(false);
  const flatListRef = useRef<FlatList>(null);

  const startConversation = useCallback(async () => {
    setLoading(true);
    try {
      const res = await api.post<{ conversation_id: string; message: string }>('/career-coach/start');
      setConversationId(res.conversation_id);
      setMessages([{ id: '1', role: 'assistant', content: res.message }]);
      setStarted(true);
    } catch {
      setMessages([{ id: '1', role: 'assistant', content: 'Sorry, I had trouble starting. Please try again.' }]);
    } finally {
      setLoading(false);
    }
  }, []);

  const sendMessage = useCallback(async () => {
    if (!input.trim() || !conversationId || loading) return;
    const text = input.trim();
    setInput('');

    setMessages((prev) => [...prev, { id: `u-${Date.now()}`, role: 'user', content: text }]);
    setLoading(true);

    try {
      const res = await api.post<{ message: string }>('/career-coach/message', {
        conversation_id: conversationId,
        message: text,
      });
      setMessages((prev) => [...prev, { id: `a-${Date.now()}`, role: 'assistant', content: res.message }]);
    } catch {
      setMessages((prev) => [...prev, { id: `e-${Date.now()}`, role: 'assistant', content: 'Something went wrong. Could you try again?' }]);
    } finally {
      setLoading(false);
    }
  }, [input, conversationId, loading]);

  if (!isEnabled('career_coach')) return null;

  return (
    <SafeAreaView style={[styles.container, { backgroundColor: colors.background }]} edges={['top']}>
      <KeyboardAvoidingView
        style={styles.keyboardView}
        behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        keyboardVerticalOffset={90}
      >
        <View style={styles.header}>
          <Pressable onPress={() => router.back()}>
            <Typography variant="small" family="sans" color={colors.textSecondary}>
              {'\u2190'} Back
            </Typography>
          </Pressable>
          <Typography variant="heading" style={styles.title}>Career Coach</Typography>
          <Typography variant="small" family="sans" color={colors.textSecondary}>
            Explore paths aligned with your calling
          </Typography>
        </View>

        <View style={[styles.divider, { backgroundColor: colors.divider }]} />

        {!started ? (
          <View style={styles.startState}>
            <Typography variant="heading" style={styles.startTitle}>
              Explore Your Path
            </Typography>
            <Typography variant="body" color={colors.textSecondary} style={styles.startBody}>
              Your AI coach knows your vocational profile and can search real job listings
              to help you find work that aligns with your calling.
            </Typography>
            <Pressable
              onPress={startConversation}
              disabled={loading}
              style={[styles.startBtn, { backgroundColor: colors.text, opacity: loading ? 0.5 : 1 }]}
            >
              <Typography variant="small" family="sans" color={colors.background}>
                {loading ? 'Starting...' : 'Start Session'}
              </Typography>
            </Pressable>
          </View>
        ) : (
          <>
            <FlatList
              ref={flatListRef}
              data={messages}
              keyExtractor={(item) => item.id}
              renderItem={({ item }) => (
                <View
                  style={[
                    styles.bubble,
                    item.role === 'user'
                      ? [styles.userBubble, { backgroundColor: colors.text }]
                      : [styles.assistantBubble, { borderColor: colors.divider }],
                  ]}
                >
                  <Typography
                    variant="body"
                    color={item.role === 'user' ? colors.background : colors.text}
                    style={styles.bubbleText}
                  >
                    {item.content}
                  </Typography>
                </View>
              )}
              contentContainerStyle={styles.messageList}
              onContentSizeChange={() => flatListRef.current?.scrollToEnd()}
              showsVerticalScrollIndicator={false}
              ListFooterComponent={loading ? (
                <View style={[styles.assistantBubble, { borderColor: colors.divider }]}>
                  <ActivityIndicator size="small" color={colors.accent} />
                </View>
              ) : null}
            />

            <View style={[styles.inputRow, { borderTopColor: colors.divider }]}>
              <TextInput
                value={input}
                onChangeText={setInput}
                placeholder="Ask about careers, jobs, or your path..."
                placeholderTextColor={colors.accent}
                style={[styles.textInput, { color: colors.text, borderColor: colors.divider }]}
                editable={!loading}
                onSubmitEditing={sendMessage}
                returnKeyType="send"
                multiline
              />
              <Pressable
                onPress={sendMessage}
                disabled={loading || !input.trim()}
                style={[styles.sendBtn, { backgroundColor: colors.text, opacity: loading || !input.trim() ? 0.5 : 1 }]}
              >
                <Typography variant="small" family="sans" color={colors.background}>Send</Typography>
              </Pressable>
            </View>
          </>
        )}
      </KeyboardAvoidingView>
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  container: { flex: 1 },
  keyboardView: { flex: 1 },
  header: { paddingHorizontal: spacing.lg, paddingTop: spacing.md, paddingBottom: spacing.sm },
  title: { marginTop: spacing.xs },
  divider: { height: 1, marginHorizontal: spacing.lg },
  startState: { flex: 1, justifyContent: 'center', alignItems: 'center', paddingHorizontal: spacing.xl },
  startTitle: { textAlign: 'center', marginBottom: spacing.md },
  startBody: { textAlign: 'center', marginBottom: spacing.xl },
  startBtn: { paddingHorizontal: spacing.xl, paddingVertical: 14 },
  messageList: { paddingHorizontal: spacing.lg, paddingVertical: spacing.md },
  bubble: { maxWidth: '85%', paddingHorizontal: spacing.md, paddingVertical: spacing.sm, marginBottom: spacing.sm },
  userBubble: { alignSelf: 'flex-end' },
  assistantBubble: { alignSelf: 'flex-start', borderWidth: 1 },
  bubbleText: { lineHeight: 20 },
  inputRow: { flexDirection: 'row', gap: spacing.sm, paddingHorizontal: spacing.lg, paddingVertical: spacing.sm, borderTopWidth: 1 },
  textInput: { flex: 1, borderWidth: 1, paddingHorizontal: spacing.sm, paddingVertical: 10, fontSize: 14, maxHeight: 100 },
  sendBtn: { paddingHorizontal: spacing.md, paddingVertical: 10, justifyContent: 'center' },
});
