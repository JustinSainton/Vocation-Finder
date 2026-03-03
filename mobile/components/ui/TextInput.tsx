import React, { useState } from 'react';
import {
  TextInput as RNTextInput,
  StyleSheet,
  View,
  TextInputProps as RNTextInputProps,
} from 'react-native';
import { colors, typography, spacing } from '../../constants/theme';

interface TextInputProps extends Omit<RNTextInputProps, 'style'> {
  minHeight?: number;
}

export function TextInput({
  minHeight = 120,
  placeholder = 'Begin typing...',
  ...props
}: TextInputProps) {
  const [height, setHeight] = useState(minHeight);

  return (
    <View style={styles.container}>
      <RNTextInput
        multiline
        placeholder={placeholder}
        placeholderTextColor={colors.accent}
        textAlignVertical="top"
        style={[
          styles.input,
          { minHeight, height: Math.max(height, minHeight) },
        ]}
        onContentSizeChange={(e) => {
          setHeight(e.nativeEvent.contentSize.height);
        }}
        {...props}
      />
    </View>
  );
}

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
