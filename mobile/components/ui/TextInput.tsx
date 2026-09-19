import React, { useState, forwardRef } from 'react';
import {
  TextInput as RNTextInput,
  StyleSheet,
  View,
  TextInputProps as RNTextInputProps,
} from 'react-native';
import { typography, spacing } from '../../constants/theme';
import { useTheme } from '../../hooks/useTheme';

interface TextInputProps extends Omit<RNTextInputProps, 'style'> {
  minHeight?: number;
}

export const TextInput = forwardRef<RNTextInput, TextInputProps>(
  function TextInput(
    { minHeight = 120, placeholder = 'Begin typing...', ...props },
    ref
  ) {
    const { colors } = useTheme();
    const [height, setHeight] = useState(minHeight);
    const styles = getStyles(colors);

    return (
      <View style={styles.container}>
        <RNTextInput
          ref={ref}
          multiline={!props.secureTextEntry}
          placeholder={placeholder}
          placeholderTextColor={colors.textSecondary}
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
);

const getStyles = (colors: {
  divider: string;
  text: string;
}) =>
  StyleSheet.create({
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
