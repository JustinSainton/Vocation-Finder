import React, { forwardRef } from 'react';
import {
  TextInput as RNTextInput,
  StyleSheet,
  View,
  KeyboardTypeOptions,
} from 'react-native';
import { colors, typography, spacing } from '../../constants/theme';

interface SingleLineInputProps {
  placeholder?: string;
  value: string;
  onChangeText: (text: string) => void;
  secureTextEntry?: boolean;
  keyboardType?: KeyboardTypeOptions;
  autoCapitalize?: 'none' | 'sentences' | 'words' | 'characters';
  autoFocus?: boolean;
  returnKeyType?: 'done' | 'next' | 'go';
  onSubmitEditing?: () => void;
}

export const SingleLineInput = forwardRef<RNTextInput, SingleLineInputProps>(
  function SingleLineInput(
    {
      placeholder = '',
      value,
      onChangeText,
      secureTextEntry = false,
      keyboardType = 'default',
      autoCapitalize = 'none',
      autoFocus = false,
      returnKeyType,
      onSubmitEditing,
    },
    ref
  ) {
    return (
      <View style={styles.container}>
        <RNTextInput
          ref={ref}
          value={value}
          onChangeText={onChangeText}
          placeholder={placeholder}
          placeholderTextColor={colors.accent}
          secureTextEntry={secureTextEntry}
          keyboardType={keyboardType}
          autoCapitalize={autoCapitalize}
          autoCorrect={false}
          autoFocus={autoFocus}
          returnKeyType={returnKeyType}
          onSubmitEditing={onSubmitEditing}
          style={styles.input}
        />
      </View>
    );
  }
);

const styles = StyleSheet.create({
  container: {
    borderBottomWidth: 1,
    borderBottomColor: colors.divider,
  },
  input: {
    fontFamily: typography.fontFamily.serif,
    fontSize: typography.sizes.body,
    lineHeight: typography.sizes.body * typography.lineHeight.body,
    color: colors.text,
    paddingVertical: spacing.md,
    paddingHorizontal: 0,
  },
});
