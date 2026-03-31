export type AssessmentLocale = 'en-US' | 'es-419' | 'pt-BR';

export interface LocaleOption {
  locale: AssessmentLocale;
  speechLocale: AssessmentLocale;
  label: string;
  nativeLabel: string;
}

export const DEFAULT_ASSESSMENT_LOCALE: AssessmentLocale = 'en-US';

export const ASSESSMENT_LOCALE_OPTIONS: LocaleOption[] = [
  {
    locale: 'en-US',
    speechLocale: 'en-US',
    label: 'English',
    nativeLabel: 'English',
  },
  {
    locale: 'es-419',
    speechLocale: 'es-419',
    label: 'Spanish',
    nativeLabel: 'Español',
  },
  {
    locale: 'pt-BR',
    speechLocale: 'pt-BR',
    label: 'Portuguese',
    nativeLabel: 'Português',
  },
];

const CATEGORY_LABELS: Record<string, Record<AssessmentLocale, string>> = {
  'service-orientation': {
    'en-US': 'Service Orientation',
    'es-419': 'Orientación de servicio',
    'pt-BR': 'Orientação de serviço',
  },
  'problem-solving-draw': {
    'en-US': 'Problem-Solving Draw',
    'es-419': 'Impulso para resolver problemas',
    'pt-BR': 'Impulso para resolver problemas',
  },
  'energy-engagement': {
    'en-US': 'Energy & Engagement',
    'es-419': 'Energía y compromiso',
    'pt-BR': 'Energia e engajamento',
  },
  'values-under-pressure': {
    'en-US': 'Values Under Pressure',
    'es-419': 'Valores bajo presión',
    'pt-BR': 'Valores sob pressão',
  },
  'suffering-limitation': {
    'en-US': 'Suffering & Limitation',
    'es-419': 'Sufrimiento y limitación',
    'pt-BR': 'Sofrimento e limitação',
  },
  'legacy-impact': {
    'en-US': 'Legacy & Impact',
    'es-419': 'Legado e impacto',
    'pt-BR': 'Legado e impacto',
  },
  'context-direction': {
    'en-US': 'Context & Direction',
    'es-419': 'Contexto y dirección',
    'pt-BR': 'Contexto e direção',
  },
};

type AssessmentCopy = {
  common: {
    retry: string;
    tryAgain: string;
    startOver: string;
    continueLabel: string;
    continueLoading: string;
    back: string;
  };
  orientation: {
    title: string;
    introOne: string;
    introTwo: string;
    timeNote: string;
    checkbox: string;
    speak: string;
    write: string;
    languageLabel: string;
  };
  written: {
    loading: string;
    noneAvailable: string;
    placeholder: string;
    finish: string;
    continueLabel: string;
    progress: (currentQuestion: number, totalQuestions: number) => string;
  };
  conversation: {
    title: string;
    tapToBegin: string;
    idle: string;
    listening: string;
    processing: string;
    speaking: string;
    error: string;
    tapToRecord: string;
    starting: string;
    missingQuestion: string;
    loadingQuestions: string;
    noSpeechDetected: string;
    intro: (totalQuestions: number, questionText: string) => string;
    progress: (currentQuestion: number, totalQuestions: number) => string;
  };
  synthesis: {
    paragraphOne: string;
    paragraphTwo: string;
    continueLabel: string;
    preparing: string;
  };
  results: {
    notReadyTitle: string;
    notReadyBody: string;
    errorTitle: string;
    takingLong: string;
    checkAgain: string;
    headings: {
      vocationalOrientation: string;
      primaryDomain: string;
      modeOfWork: string;
      secondaryOrientation: string;
      primaryPathways: string;
      specificConsiderations: string;
      nextSteps: string;
      ministryIntegration: string;
      saveResults: string;
    };
    emailPrompt: string;
    emailPlaceholder: string;
    emailSent: string;
    emailSend: string;
    emailSending: string;
    emailErrorTitle: string;
    emailErrorBody: string;
    returnHome: string;
    retake: string;
    disclaimer: string;
  };
};

const COPY: Record<AssessmentLocale, AssessmentCopy> = {
  'en-US': {
    common: {
      retry: 'Retry',
      tryAgain: 'Try again',
      startOver: 'Start over',
      continueLabel: 'Continue',
      continueLoading: 'Preparing...',
      back: 'Back',
    },
    orientation: {
      title: 'Before we begin',
      introOne:
        'This is not a test. There are no right answers, no scores, and no judgment. The questions ahead are invitations to think honestly about what moves you, what frustrates you, and what you find yourself returning to again and again.',
      introTwo:
        'Set aside roughly 30-45 minutes. This is best done in a quiet place, without distractions, when you can give your full attention to the process.',
      timeNote: '~30-45 minutes',
      checkbox: "I'm willing to answer honestly, not impressively.",
      speak: 'Speak your answers',
      write: 'Write your answers',
      languageLabel: 'Choose your language',
    },
    written: {
      loading: 'Preparing your questions...',
      noneAvailable: 'No questions are available yet. Please try again shortly.',
      placeholder: 'Take your time. Write freely.',
      finish: 'Finish assessment',
      continueLabel: 'Continue',
      progress: (currentQuestion, totalQuestions) =>
        `Question ${currentQuestion + 1} of ${totalQuestions}`,
    },
    conversation: {
      title: 'Voice Discernment',
      tapToBegin: 'Tap to begin',
      idle: 'Tap the orb to answer',
      listening: 'Listening... tap again to send',
      processing: 'Discerning your response...',
      speaking: 'Speaking... tap orb to skip',
      error: 'Connection issue. Tap to retry.',
      tapToRecord: 'Tap to start recording',
      starting: 'Starting conversation...',
      missingQuestion: 'No question is ready yet. Please try again in a moment.',
      loadingQuestions: 'Questions are still loading. Please try again in a moment.',
      noSpeechDetected:
        'No speech detected. Please speak a little closer to the mic and try again.',
      intro: (totalQuestions, questionText) =>
        totalQuestions > 0
          ? `Welcome to Vocation Finder. We'll walk through ${totalQuestions} questions together. Take your time and answer honestly. First question: ${questionText}`
          : `Welcome to Vocation Finder. First question: ${questionText}`,
      progress: (currentQuestion, totalQuestions) =>
        `Question ${currentQuestion + 1} of ${totalQuestions}`,
    },
    synthesis: {
      paragraphOne:
        "We're now looking for patterns across what you shared — not isolated answers, but the story they tell together.",
      paragraphTwo:
        'Your reflections deserve careful attention. What comes next is not a summary — it is an articulation of what already lives within your responses.',
      continueLabel: 'Continue',
      preparing: 'Preparing...',
    },
    results: {
      notReadyTitle: 'Your vocational portrait is being prepared.',
      notReadyBody:
        'This may take a moment. Your reflections deserve careful attention.',
      errorTitle: 'We could not complete your assessment yet.',
      takingLong:
        'This is taking longer than expected. If it remains stuck, the background analysis worker may be paused.',
      checkAgain: 'Check again',
      headings: {
        vocationalOrientation: 'Vocational Orientation',
        primaryDomain: 'Primary Domain',
        modeOfWork: 'Mode of Work',
        secondaryOrientation: 'Secondary Orientation',
        primaryPathways: 'Primary Pathways',
        specificConsiderations: 'Specific Considerations',
        nextSteps: 'Next Steps',
        ministryIntegration: 'Ministry Integration',
        saveResults: 'Save your results',
      },
      emailPrompt:
        'Enter your email to receive a beautifully formatted copy of your vocational portrait.',
      emailPlaceholder: 'your@email.com',
      emailSent: 'Sent — check your inbox.',
      emailSend: 'Send',
      emailSending: 'Sending...',
      emailErrorTitle: 'Could not send',
      emailErrorBody: 'We were unable to email your results. Please try again later.',
      returnHome: 'Return home',
      retake: 'Take assessment again',
      disclaimer:
        'This vocational portrait was generated with the assistance of artificial intelligence based on your reflections. It is intended as a tool for discernment, not a definitive assessment. We encourage you to discuss these results with a trusted mentor, spiritual director, or counselor.',
    },
  },
  'es-419': {
    common: {
      retry: 'Reintentar',
      tryAgain: 'Intentarlo de nuevo',
      startOver: 'Empezar de nuevo',
      continueLabel: 'Continuar',
      continueLoading: 'Preparando...',
      back: 'Atrás',
    },
    orientation: {
      title: 'Antes de comenzar',
      introOne:
        'Esto no es una prueba. No hay respuestas correctas, puntajes ni juicio. Las preguntas que siguen son una invitación a pensar con honestidad en lo que te mueve, lo que te frustra y aquello a lo que vuelves una y otra vez.',
      introTwo:
        'Reserva aproximadamente entre 30 y 45 minutos. Lo ideal es hacerlo en un lugar tranquilo, sin distracciones, cuando puedas prestar toda tu atención al proceso.',
      timeNote: '~30-45 minutos',
      checkbox: 'Estoy dispuesto(a) a responder con honestidad, no para impresionar.',
      speak: 'Responder con voz',
      write: 'Responder por escrito',
      languageLabel: 'Elige tu idioma',
    },
    written: {
      loading: 'Preparando tus preguntas...',
      noneAvailable: 'Todavía no hay preguntas disponibles. Inténtalo de nuevo pronto.',
      placeholder: 'Tómate tu tiempo. Escribe con libertad.',
      finish: 'Terminar evaluación',
      continueLabel: 'Continuar',
      progress: (currentQuestion, totalQuestions) =>
        `Pregunta ${currentQuestion + 1} de ${totalQuestions}`,
    },
    conversation: {
      title: 'Discernimiento por voz',
      tapToBegin: 'Toca para comenzar',
      idle: 'Toca la esfera para responder',
      listening: 'Escuchando... toca otra vez para enviar',
      processing: 'Discerniendo tu respuesta...',
      speaking: 'Hablando... toca la esfera para omitir',
      error: 'Hubo un problema de conexión. Toca para intentar de nuevo.',
      tapToRecord: 'Toca para empezar a grabar',
      starting: 'Iniciando conversación...',
      missingQuestion: 'Todavía no hay una pregunta lista. Inténtalo de nuevo en un momento.',
      loadingQuestions: 'Las preguntas aún se están cargando. Inténtalo de nuevo en un momento.',
      noSpeechDetected:
        'No detectamos voz. Acércate un poco más al micrófono e inténtalo de nuevo.',
      intro: (totalQuestions, questionText) =>
        totalQuestions > 0
          ? `Bienvenido a Vocation Finder. Vamos a recorrer ${totalQuestions} preguntas juntos. Tómate tu tiempo y responde con honestidad. Primera pregunta: ${questionText}`
          : `Bienvenido a Vocation Finder. Primera pregunta: ${questionText}`,
      progress: (currentQuestion, totalQuestions) =>
        `Pregunta ${currentQuestion + 1} de ${totalQuestions}`,
    },
    synthesis: {
      paragraphOne:
        'Ahora estamos buscando patrones en lo que compartiste, no respuestas aisladas, sino la historia que cuentan en conjunto.',
      paragraphTwo:
        'Tus reflexiones merecen atención cuidadosa. Lo que sigue no es un resumen, sino una articulación de lo que ya vive dentro de tus respuestas.',
      continueLabel: 'Continuar',
      preparing: 'Preparando...',
    },
    results: {
      notReadyTitle: 'Tu retrato vocacional se está preparando.',
      notReadyBody:
        'Esto puede tardar un momento. Tus reflexiones merecen atención cuidadosa.',
      errorTitle: 'Todavía no pudimos completar tu evaluación.',
      takingLong:
        'Esto está tardando más de lo esperado. Si sigue detenido, es posible que el proceso de análisis en segundo plano esté pausado.',
      checkAgain: 'Revisar de nuevo',
      headings: {
        vocationalOrientation: 'Orientación vocacional',
        primaryDomain: 'Dominio principal',
        modeOfWork: 'Modo de trabajo',
        secondaryOrientation: 'Orientación secundaria',
        primaryPathways: 'Caminos principales',
        specificConsiderations: 'Consideraciones específicas',
        nextSteps: 'Próximos pasos',
        ministryIntegration: 'Integración con el ministerio',
        saveResults: 'Guarda tus resultados',
      },
      emailPrompt:
        'Ingresa tu correo para recibir una copia bien presentada de tu retrato vocacional.',
      emailPlaceholder: 'tu@correo.com',
      emailSent: 'Enviado. Revisa tu bandeja de entrada.',
      emailSend: 'Enviar',
      emailSending: 'Enviando...',
      emailErrorTitle: 'No se pudo enviar',
      emailErrorBody:
        'No pudimos enviarte tus resultados por correo. Inténtalo de nuevo más tarde.',
      returnHome: 'Volver al inicio',
      retake: 'Hacer la evaluación otra vez',
      disclaimer:
        'Este retrato vocacional fue generado con ayuda de inteligencia artificial a partir de tus reflexiones. Está pensado como una herramienta de discernimiento, no como una evaluación definitiva. Te animamos a conversar sobre estos resultados con un mentor de confianza, un director espiritual o un consejero.',
    },
  },
  'pt-BR': {
    common: {
      retry: 'Tentar novamente',
      tryAgain: 'Tentar de novo',
      startOver: 'Começar de novo',
      continueLabel: 'Continuar',
      continueLoading: 'Preparando...',
      back: 'Voltar',
    },
    orientation: {
      title: 'Antes de começarmos',
      introOne:
        'Isto não é um teste. Não há respostas certas, pontuações nem julgamento. As perguntas a seguir são um convite para pensar com honestidade sobre o que te move, o que te frustra e aquilo ao qual você volta repetidamente.',
      introTwo:
        'Separe cerca de 30 a 45 minutos. O ideal é fazer isso em um lugar tranquilo, sem distrações, quando você puder dar atenção total ao processo.',
      timeNote: '~30-45 minutos',
      checkbox: 'Estou disposto(a) a responder com honestidade, não para impressionar.',
      speak: 'Responder falando',
      write: 'Responder escrevendo',
      languageLabel: 'Escolha seu idioma',
    },
    written: {
      loading: 'Preparando suas perguntas...',
      noneAvailable: 'Ainda não há perguntas disponíveis. Tente novamente em breve.',
      placeholder: 'Leve o tempo que precisar. Escreva com liberdade.',
      finish: 'Concluir avaliação',
      continueLabel: 'Continuar',
      progress: (currentQuestion, totalQuestions) =>
        `Pergunta ${currentQuestion + 1} de ${totalQuestions}`,
    },
    conversation: {
      title: 'Discernimento por voz',
      tapToBegin: 'Toque para começar',
      idle: 'Toque na esfera para responder',
      listening: 'Ouvindo... toque novamente para enviar',
      processing: 'Discernindo sua resposta...',
      speaking: 'Falando... toque na esfera para pular',
      error: 'Houve um problema de conexão. Toque para tentar novamente.',
      tapToRecord: 'Toque para começar a gravar',
      starting: 'Iniciando conversa...',
      missingQuestion: 'Ainda não há uma pergunta pronta. Tente novamente em instantes.',
      loadingQuestions: 'As perguntas ainda estão carregando. Tente novamente em instantes.',
      noSpeechDetected:
        'Nenhuma fala foi detectada. Chegue um pouco mais perto do microfone e tente novamente.',
      intro: (totalQuestions, questionText) =>
        totalQuestions > 0
          ? `Bem-vindo ao Vocation Finder. Vamos percorrer ${totalQuestions} perguntas juntos. Leve o tempo que precisar e responda com honestidade. Primeira pergunta: ${questionText}`
          : `Bem-vindo ao Vocation Finder. Primeira pergunta: ${questionText}`,
      progress: (currentQuestion, totalQuestions) =>
        `Pergunta ${currentQuestion + 1} de ${totalQuestions}`,
    },
    synthesis: {
      paragraphOne:
        'Agora estamos procurando padrões no que você compartilhou, não respostas isoladas, mas a história que elas contam juntas.',
      paragraphTwo:
        'Suas reflexões merecem atenção cuidadosa. O que vem a seguir não é um resumo, mas uma articulação do que já vive dentro das suas respostas.',
      continueLabel: 'Continuar',
      preparing: 'Preparando...',
    },
    results: {
      notReadyTitle: 'Seu retrato vocacional está sendo preparado.',
      notReadyBody:
        'Isso pode levar um momento. Suas reflexões merecem atenção cuidadosa.',
      errorTitle: 'Ainda não conseguimos concluir sua avaliação.',
      takingLong:
        'Isso está demorando mais do que o esperado. Se continuar parado, o processo de análise em segundo plano pode estar pausado.',
      checkAgain: 'Verificar novamente',
      headings: {
        vocationalOrientation: 'Orientação vocacional',
        primaryDomain: 'Domínio principal',
        modeOfWork: 'Modo de trabalho',
        secondaryOrientation: 'Orientação secundária',
        primaryPathways: 'Caminhos principais',
        specificConsiderations: 'Considerações específicas',
        nextSteps: 'Próximos passos',
        ministryIntegration: 'Integração ministerial',
        saveResults: 'Guarde seus resultados',
      },
      emailPrompt:
        'Digite seu e-mail para receber uma cópia bem formatada do seu retrato vocacional.',
      emailPlaceholder: 'voce@email.com',
      emailSent: 'Enviado. Confira sua caixa de entrada.',
      emailSend: 'Enviar',
      emailSending: 'Enviando...',
      emailErrorTitle: 'Não foi possível enviar',
      emailErrorBody:
        'Não foi possível enviar seus resultados por e-mail. Tente novamente mais tarde.',
      returnHome: 'Voltar ao início',
      retake: 'Fazer a avaliação novamente',
      disclaimer:
        'Este retrato vocacional foi gerado com a ajuda de inteligência artificial com base nas suas reflexões. Ele foi pensado como uma ferramenta de discernimento, não como uma avaliação definitiva. Recomendamos conversar sobre esses resultados com um mentor de confiança, diretor espiritual ou conselheiro.',
    },
  },
};

export function normalizeAssessmentLocale(locale?: string | null): AssessmentLocale {
  const normalized = locale?.trim().replace('_', '-').toLowerCase();

  if (!normalized) {
    return DEFAULT_ASSESSMENT_LOCALE;
  }

  if (normalized === 'es' || normalized.startsWith('es-')) {
    return 'es-419';
  }

  if (normalized === 'pt' || normalized.startsWith('pt-')) {
    return 'pt-BR';
  }

  return 'en-US';
}

export function getLocaleOption(locale?: string | null): LocaleOption {
  const normalized = normalizeAssessmentLocale(locale);

  return (
    ASSESSMENT_LOCALE_OPTIONS.find((option) => option.locale === normalized) ??
    ASSESSMENT_LOCALE_OPTIONS[0]
  );
}

export function getAssessmentCopy(locale?: string | null): AssessmentCopy {
  return COPY[normalizeAssessmentLocale(locale)];
}

export function getExpoSpeechLanguage(locale?: string | null): string {
  return {
    'en-US': 'en-US',
    'es-419': 'es-MX',
    'pt-BR': 'pt-BR',
  }[normalizeAssessmentLocale(locale)];
}

export function getSpeechRecognitionLanguage(locale?: string | null): 'en' | 'es' | 'pt' {
  switch (normalizeAssessmentLocale(locale)) {
    case 'es-419':
      return 'es';
    case 'pt-BR':
      return 'pt';
    default:
      return 'en';
  }
}

export function translateQuestionCategory(
  categorySlug: string | null | undefined,
  locale?: string | null,
  fallbackName?: string | null
): string | null {
  if (!categorySlug) {
    return fallbackName ?? null;
  }

  const normalizedLocale = normalizeAssessmentLocale(locale);
  const localized = CATEGORY_LABELS[categorySlug]?.[normalizedLocale];

  return localized ?? fallbackName ?? null;
}
