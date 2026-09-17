#!/bin/bash
# Generate pre-cached question audio using ElevenLabs TTS API
# Voice: Charlotte (XB0fDUnXU5powFXDhCwa)
# Model: eleven_multilingual_v2 (best quality for pre-generation)

set -e

API_KEY="$1"
if [ -z "$API_KEY" ]; then
  echo "Usage: $0 <elevenlabs-api-key>"
  exit 1
fi

VOICE_ID="XB0fDUnXU5powFXDhCwa"
MODEL_ID="eleven_multilingual_v2"
OUTPUT_DIR="$(dirname "$0")/../mobile/assets/audio/questions"
mkdir -p "$OUTPUT_DIR"

generate() {
  local filename="$1"
  local text="$2"
  local output="$OUTPUT_DIR/$filename"

  if [ -f "$output" ]; then
    echo "  SKIP $filename (exists)"
    return
  fi

  echo "  GEN  $filename"
  curl -s -X POST "https://api.elevenlabs.io/v1/text-to-speech/$VOICE_ID" \
    -H "xi-api-key: $API_KEY" \
    -H "Content-Type: application/json" \
    -o "$output" \
    -d "{
      \"text\": $(echo "$text" | python3 -c 'import sys,json; print(json.dumps(sys.stdin.read().strip()))'),
      \"model_id\": \"$MODEL_ID\",
      \"voice_settings\": {
        \"stability\": 0.5,
        \"similarity_boost\": 0.75,
        \"style\": 0.4,
        \"speed\": 0.92
      }
    }"

  # Check file is valid MP3 (not an error JSON)
  if head -c 4 "$output" | grep -q '{'; then
    echo "  ERR  $filename - API error:"
    cat "$output"
    rm -f "$output"
    return 1
  fi

  local size=$(wc -c < "$output" | tr -d ' ')
  echo "       → $size bytes"
}

# ── English (en-US) ──────────────────────────────────────────

echo "=== English (en-US) ==="

generate "intro_en-US.mp3" "Welcome to your vocational discernment. I'm going to ask you twenty questions designed to help uncover your natural gifts, deepest motivations, and the kind of work you were made for. Take your time with each answer. There are no right or wrong responses — just honest ones. Let's begin."

generate "q01_en-US.mp3" "I'd like to start by thinking about a time you helped someone — not because you had to, but because you genuinely wanted to. Can you tell me about that? What did you do, and what made you want to help in that way?"

generate "q02_en-US.mp3" "Now imagine a friend comes to you with a problem. What kind of problem would genuinely make you think, 'I want to help with this'? Walk me through what that situation looks like and what you'd naturally do."

generate "q03_en-US.mp3" "When you picture making a real difference in someone's life, what does that actually look like to you? Be as specific and detailed as you can."

generate "q04_en-US.mp3" "Let me ask you something different. What's something in the world — big or small — that really bothers you or frustrates you? It could be in your school, community, or the wider world. What is it, and why does it get to you?"

generate "q05_en-US.mp3" "If you could fix one thing about how the world works, what would you choose? Tell me what feels broken, and what you wish it looked like instead."

generate "q06_en-US.mp3" "Now think about something most people seem to ignore or accept as just the way things are — but you can't stop thinking about it. What is it, and why can't you let it go?"

generate "q07_en-US.mp3" "I want to shift to thinking about energy. Can you describe a time when you were working on something — anything — and you completely lost track of time? What were you doing, and what made it so absorbing?"

generate "q08_en-US.mp3" "Think about a class, project, or activity that actually energized you — where you came out of it with more energy than you went in. What were you doing, and what made it feel different?"

generate "q09_en-US.mp3" "What's something you've made, built, organized, or accomplished that you're genuinely proud of? Tell me about what you did and why it mattered to you."

generate "q10_en-US.mp3" "If you had a completely free day with no obligations, and you could work on anything you wanted — what would you choose? I'm looking for something specific, not just 'relax' or 'hang out.'"

generate "q11_en-US.mp3" "Now I want to ask about values. Describe a time you had to choose between two things you genuinely cared about — maybe between helping someone and meeting your own obligations, or between safety and risk. What did you choose, and why?"

generate "q12_en-US.mp3" "Think about a decision you made that people around you — friends, family — didn't really understand or support. What was it, and why did you do it anyway?"

generate "q13_en-US.mp3" "Have you ever had to choose between what people expected of you and what you actually felt drawn to do? Tell me what happened and how you worked through it."

generate "q14_en-US.mp3" "I want to ask about something harder now. Describe a time when something you really wanted didn't work out — maybe you didn't get accepted somewhere, or failed at something that mattered. How did you respond, and what did you do next?"

generate "q15_en-US.mp3" "What's something that limits what you can do right now — money, location, family, grades, anything? How do you think about that limitation? Is it something to overcome, or does it help you see your path more clearly?"

generate "q16_en-US.mp3" "Let's think forward. Imagine you're 40 years old and someone asks, 'What do you do?' How do you hope you'd answer? What kind of work do you hope you'll be doing, and why would it matter?"

generate "q17_en-US.mp3" "If you could spend your career actually making progress on one specific problem — not just talking about it, but doing something real — what problem would you choose, and why that one?"

generate "q18_en-US.mp3" "Think about the impact you want your life to have. When you're older, what do you want people to say about how your work affected them or made things better?"

generate "q19_en-US.mp3" "Almost done. What are you actually good at — not what you wish you were good at, but what do people come to you for? What do others say you do well? Give me specific examples."

generate "q20_en-US.mp3" "Last question. What majors or career paths are you considering right now, even if you're unsure? What draws you to those options, and what makes you hesitate?"

# ── Spanish (es-419) ──────────────────────────────────────────

echo "=== Spanish (es-419) ==="

generate "intro_es-419.mp3" "Bienvenido a tu discernimiento vocacional. Voy a hacerte veinte preguntas diseñadas para ayudarte a descubrir tus dones naturales, tus motivaciones más profundas y el tipo de trabajo para el que fuiste hecho. Tómate tu tiempo con cada respuesta. No hay respuestas correctas ni incorrectas, solo honestas. Comencemos."

generate "q01_es-419.mp3" "Me gustaría comenzar pensando en una vez que ayudaste a alguien, no porque tuvieras que hacerlo, sino porque genuinamente querías. ¿Puedes contarme sobre eso? ¿Qué hiciste y qué te hizo querer ayudar de esa manera?"

generate "q02_es-419.mp3" "Ahora imagina que un amigo viene a ti con un problema. ¿Qué tipo de problema genuinamente te haría pensar, 'Quiero ayudar con esto'? Descríbeme cómo se ve esa situación y qué harías naturalmente."

generate "q03_es-419.mp3" "Cuando te imaginas haciendo una diferencia real en la vida de alguien, ¿cómo se ve realmente eso para ti? Sé lo más específico y detallado que puedas."

generate "q04_es-419.mp3" "Déjame preguntarte algo diferente. ¿Qué es algo en el mundo — grande o pequeño — que realmente te molesta o te frustra? Puede ser en tu escuela, comunidad o el mundo en general. ¿Qué es y por qué te afecta?"

generate "q05_es-419.mp3" "Si pudieras arreglar una cosa sobre cómo funciona el mundo, ¿qué elegirías? Dime qué se siente roto y cómo desearías que fuera en su lugar."

generate "q06_es-419.mp3" "Ahora piensa en algo que la mayoría de las personas parecen ignorar o aceptar como simplemente la forma en que son las cosas, pero tú no puedes dejar de pensar en ello. ¿Qué es y por qué no puedes dejarlo ir?"

generate "q07_es-419.mp3" "Quiero cambiar a pensar sobre energía. ¿Puedes describir una vez en la que estabas trabajando en algo — lo que sea — y perdiste completamente la noción del tiempo? ¿Qué estabas haciendo y qué lo hizo tan absorbente?"

generate "q08_es-419.mp3" "Piensa en una clase, proyecto o actividad que realmente te energizó — donde saliste con más energía de la que tenías al entrar. ¿Qué estabas haciendo y qué lo hizo sentir diferente?"

generate "q09_es-419.mp3" "¿Qué es algo que hayas hecho, construido, organizado o logrado de lo que estés genuinamente orgulloso? Cuéntame sobre lo que hiciste y por qué te importó."

generate "q10_es-419.mp3" "Si tuvieras un día completamente libre sin obligaciones, y pudieras trabajar en lo que quisieras — ¿qué elegirías? Busco algo específico, no solo 'descansar' o 'pasar el rato.'"

generate "q11_es-419.mp3" "Ahora quiero preguntarte sobre valores. Describe una vez en la que tuviste que elegir entre dos cosas que genuinamente te importaban — tal vez entre ayudar a alguien y cumplir con tus propias obligaciones, o entre seguridad y riesgo. ¿Qué elegiste y por qué?"

generate "q12_es-419.mp3" "Piensa en una decisión que tomaste que las personas a tu alrededor — amigos, familia — realmente no entendieron o apoyaron. ¿Cuál fue y por qué lo hiciste de todos modos?"

generate "q13_es-419.mp3" "¿Alguna vez has tenido que elegir entre lo que la gente esperaba de ti y lo que realmente te sentías atraído a hacer? Cuéntame qué pasó y cómo lo resolviste."

generate "q14_es-419.mp3" "Quiero preguntarte sobre algo más difícil ahora. Describe una vez en la que algo que realmente querías no funcionó — tal vez no fuiste aceptado en algún lugar, o fallaste en algo que importaba. ¿Cómo respondiste y qué hiciste después?"

generate "q15_es-419.mp3" "¿Qué es algo que limita lo que puedes hacer ahora mismo — dinero, ubicación, familia, calificaciones, cualquier cosa? ¿Cómo piensas sobre esa limitación? ¿Es algo que superar, o te ayuda a ver tu camino más claramente?"

generate "q16_es-419.mp3" "Pensemos hacia adelante. Imagina que tienes 40 años y alguien te pregunta, '¿A qué te dedicas?' ¿Cómo esperas responder? ¿Qué tipo de trabajo esperas estar haciendo y por qué importaría?"

generate "q17_es-419.mp3" "Si pudieras dedicar tu carrera a hacer un progreso real en un problema específico — no solo hablar de ello, sino hacer algo real — ¿qué problema elegirías y por qué ese?"

generate "q18_es-419.mp3" "Piensa en el impacto que quieres que tu vida tenga. Cuando seas mayor, ¿qué quieres que la gente diga sobre cómo tu trabajo los afectó o mejoró las cosas?"

generate "q19_es-419.mp3" "Casi terminamos. ¿En qué eres realmente bueno — no en lo que desearías ser bueno, sino en aquello por lo que la gente recurre a ti? ¿Qué dicen los demás que haces bien? Dame ejemplos específicos."

generate "q20_es-419.mp3" "Última pregunta. ¿Qué carreras o caminos profesionales estás considerando ahora mismo, aunque no estés seguro? ¿Qué te atrae de esas opciones y qué te hace dudar?"

# ── Portuguese (pt-BR) ──────────────────────────────────────────

echo "=== Portuguese (pt-BR) ==="

generate "intro_pt-BR.mp3" "Bem-vindo ao seu discernimento vocacional. Vou fazer vinte perguntas projetadas para ajudar a descobrir seus dons naturais, suas motivações mais profundas e o tipo de trabalho para o qual você foi feito. Tome seu tempo com cada resposta. Não há respostas certas ou erradas — apenas honestas. Vamos começar."

generate "q01_pt-BR.mp3" "Gostaria de começar pensando em uma vez que você ajudou alguém — não porque tinha que fazer, mas porque genuinamente queria. Pode me contar sobre isso? O que você fez e o que te fez querer ajudar dessa forma?"

generate "q02_pt-BR.mp3" "Agora imagine que um amigo vem até você com um problema. Que tipo de problema genuinamente te faria pensar, 'Eu quero ajudar com isso'? Me descreva como seria essa situação e o que você naturalmente faria."

generate "q03_pt-BR.mp3" "Quando você imagina fazendo uma diferença real na vida de alguém, como isso realmente se parece para você? Seja o mais específico e detalhado que puder."

generate "q04_pt-BR.mp3" "Deixe-me perguntar algo diferente. O que é algo no mundo — grande ou pequeno — que realmente te incomoda ou frustra? Pode ser na sua escola, comunidade ou no mundo em geral. O que é e por que te afeta?"

generate "q05_pt-BR.mp3" "Se você pudesse consertar uma coisa sobre como o mundo funciona, o que escolheria? Me diga o que parece estar quebrado e como você gostaria que fosse."

generate "q06_pt-BR.mp3" "Agora pense em algo que a maioria das pessoas parece ignorar ou aceitar como simplesmente o jeito que as coisas são — mas você não consegue parar de pensar nisso. O que é e por que você não consegue deixar isso de lado?"

generate "q07_pt-BR.mp3" "Quero mudar para pensar sobre energia. Pode descrever uma vez em que estava trabalhando em algo — qualquer coisa — e perdeu completamente a noção do tempo? O que estava fazendo e o que tornou tão absorvente?"

generate "q08_pt-BR.mp3" "Pense em uma aula, projeto ou atividade que realmente te energizou — onde você saiu com mais energia do que quando entrou. O que estava fazendo e o que fez parecer diferente?"

generate "q09_pt-BR.mp3" "O que é algo que você fez, construiu, organizou ou realizou do qual tem genuinamente orgulho? Me conte sobre o que fez e por que isso importou para você."

generate "q10_pt-BR.mp3" "Se você tivesse um dia completamente livre sem obrigações, e pudesse trabalhar em qualquer coisa que quisesse — o que escolheria? Estou procurando algo específico, não apenas 'descansar' ou 'ficar à toa.'"

generate "q11_pt-BR.mp3" "Agora quero perguntar sobre valores. Descreva uma vez em que teve que escolher entre duas coisas que genuinamente se importava — talvez entre ajudar alguém e cumprir suas próprias obrigações, ou entre segurança e risco. O que escolheu e por quê?"

generate "q12_pt-BR.mp3" "Pense em uma decisão que você tomou que as pessoas ao seu redor — amigos, família — realmente não entenderam ou apoiaram. Qual foi e por que você fez isso mesmo assim?"

generate "q13_pt-BR.mp3" "Você já teve que escolher entre o que as pessoas esperavam de você e o que realmente se sentia atraído a fazer? Me conte o que aconteceu e como você resolveu isso."

generate "q14_pt-BR.mp3" "Quero perguntar sobre algo mais difícil agora. Descreva uma vez em que algo que você realmente queria não deu certo — talvez não foi aceito em algum lugar, ou falhou em algo que importava. Como você respondeu e o que fez depois?"

generate "q15_pt-BR.mp3" "O que é algo que limita o que você pode fazer agora — dinheiro, localização, família, notas, qualquer coisa? Como você pensa sobre essa limitação? É algo para superar, ou te ajuda a ver seu caminho mais claramente?"

generate "q16_pt-BR.mp3" "Vamos pensar adiante. Imagine que você tem 40 anos e alguém pergunta, 'O que você faz?' Como espera responder? Que tipo de trabalho espera estar fazendo e por que importaria?"

generate "q17_pt-BR.mp3" "Se pudesse dedicar sua carreira a fazer um progresso real em um problema específico — não apenas falar sobre isso, mas fazer algo real — que problema escolheria e por que esse?"

generate "q18_pt-BR.mp3" "Pense no impacto que quer que sua vida tenha. Quando for mais velho, o que quer que as pessoas digam sobre como seu trabalho as afetou ou melhorou as coisas?"

generate "q19_pt-BR.mp3" "Quase terminando. No que você é realmente bom — não no que gostaria de ser bom, mas no que as pessoas procuram você? O que os outros dizem que você faz bem? Me dê exemplos específicos."

generate "q20_pt-BR.mp3" "Última pergunta. Que cursos ou caminhos profissionais você está considerando agora, mesmo que não tenha certeza? O que te atrai nessas opções e o que te faz hesitar?"

echo ""
echo "=== Done ==="
ls -la "$OUTPUT_DIR" | wc -l
echo "files generated in $OUTPUT_DIR"
