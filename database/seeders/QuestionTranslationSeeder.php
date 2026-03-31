<?php

namespace Database\Seeders;

use App\Models\Question;
use App\Models\QuestionTranslation;
use App\Support\ConversationLocale;
use Illuminate\Database\Seeder;

class QuestionTranslationSeeder extends Seeder
{
    public function run(): void
    {
        $questions = Question::orderBy('sort_order')->get()->keyBy('sort_order');

        foreach ($questions as $question) {
            QuestionTranslation::updateOrCreate(
                [
                    'question_id' => $question->id,
                    'locale' => ConversationLocale::DEFAULT,
                ],
                [
                    'question_text' => $question->question_text,
                    'conversation_prompt' => $question->conversation_prompt,
                    'follow_up_prompts' => $question->follow_up_prompts ?? [],
                ]
            );
        }

        foreach ($this->spanishTranslations() as $sortOrder => $translation) {
            $question = $questions->get($sortOrder);
            if (! $question) {
                continue;
            }

            QuestionTranslation::updateOrCreate(
                [
                    'question_id' => $question->id,
                    'locale' => 'es-419',
                ],
                $translation
            );
        }

        foreach ($this->portugueseTranslations() as $sortOrder => $translation) {
            $question = $questions->get($sortOrder);
            if (! $question) {
                continue;
            }

            QuestionTranslation::updateOrCreate(
                [
                    'question_id' => $question->id,
                    'locale' => 'pt-BR',
                ],
                $translation
            );
        }
    }

    /**
     * @return array<int, array{question_text:string, conversation_prompt:string, follow_up_prompts: array<int, string>}>
     */
    protected function spanishTranslations(): array
    {
        return [
            1 => [
                'question_text' => 'Piensa en una ocasión en la que ayudaste a alguien y realmente se sintió significativo, no solo porque se suponía que debías hacerlo, sino porque de verdad quisiste hacerlo. ¿Qué hiciste? ¿Qué te hizo querer ayudar de esa manera?',
                'conversation_prompt' => 'Me gustaría empezar pensando en una ocasión en la que ayudaste a alguien, no porque tuvieras que hacerlo, sino porque realmente quisiste. ¿Puedes contarme sobre eso? ¿Qué hiciste y qué te hizo querer ayudar de esa manera?',
                'follow_up_prompts' => [
                    '¿Qué tuvo esa situación en particular que hizo que te involucraras?',
                    '¿Cómo te sentiste mientras ayudabas? ¿Hubo algún momento que se te haya quedado grabado?',
                    '¿Dirías que ese tipo de ayuda es algo que haces con frecuencia?',
                ],
            ],
            2 => [
                'question_text' => 'Imagina que un amigo llega a ti con un problema. ¿Qué tipo de problema te haría pensar: "De verdad quiero ayudar con esto"? Describe la situación y lo que naturalmente querrías hacer para ayudar.',
                'conversation_prompt' => 'Ahora imagina que un amigo llega a ti con un problema. ¿Qué tipo de problema te haría pensar sinceramente: "Quiero ayudar con esto"? Cuéntame cómo sería esa situación y qué harías naturalmente para ayudar.',
                'follow_up_prompts' => [
                    '¿Qué tiene ese tipo de problema que te da energía en lugar de agotarte?',
                    '¿Sueles lanzarte a ayudar de forma práctica o primero te inclinas más por escuchar y comprender?',
                ],
            ],
            3 => [
                'question_text' => 'Cuando piensas en hacer una diferencia en la vida de alguien, ¿cómo se ve eso? Describe una manera específica en la que te gustaría ayudar a las personas; sé lo más detallado posible.',
                'conversation_prompt' => 'Cuando imaginas hacer una diferencia real en la vida de alguien, ¿cómo se ve eso para ti? Sé lo más específico y detallado que puedas.',
                'follow_up_prompts' => [
                    '¿Eso tiene más que ver con impactar profundamente a una persona, o te imaginas ayudando a muchas a la vez?',
                    '¿Qué te haría sentir que realmente lograste esa diferencia?',
                ],
            ],
            4 => [
                'question_text' => '¿Qué es algo en el mundo, grande o pequeño, que realmente te molesta o te frustra? Puede ser algo en tu escuela, tu comunidad o en la sociedad en general. ¿Qué es y por qué te afecta tanto?',
                'conversation_prompt' => 'Quiero preguntarte algo diferente. ¿Qué es algo en el mundo, grande o pequeño, que realmente te molesta o te frustra? Puede ser en tu escuela, tu comunidad o en el mundo en general. ¿Qué es y por qué te afecta tanto?',
                'follow_up_prompts' => [
                    'Cuando piensas en eso, ¿sientes ganas de arreglarlo, o más bien es que no puedes dejar de notarlo?',
                    '¿Esa frustración te ha llevado alguna vez a intentar hacer algo al respecto?',
                ],
            ],
            5 => [
                'question_text' => 'Si pudieras arreglar una sola cosa sobre cómo funciona el mundo, ¿qué sería? Describe qué está roto y cómo te gustaría que fuera diferente.',
                'conversation_prompt' => 'Si pudieras arreglar una sola cosa de cómo funciona el mundo, ¿qué elegirías? Cuéntame qué sientes que está roto y cómo te gustaría que fuera en su lugar.',
                'follow_up_prompts' => [
                    '¿Qué te hace sentir que tú eres el tipo de persona que debería trabajar en ese problema?',
                    '¿Es algo que sientes desde hace mucho tiempo, o es más reciente?',
                ],
            ],
            6 => [
                'question_text' => 'Piensa en un problema que has notado y que la mayoría de las personas parecen ignorar o aceptar como normal, pero que tú no puedes dejar de pensar. ¿Qué es y por qué no puedes soltarlo?',
                'conversation_prompt' => 'Ahora piensa en algo que la mayoría de las personas parecen ignorar o aceptar como "así son las cosas", pero que tú no puedes dejar de pensar. ¿Qué es y por qué no puedes soltarlo?',
                'follow_up_prompts' => [
                    '¿Sientes que ves ese problema de una manera distinta a como lo ven la mayoría?',
                    'Si pudieras dedicar tu carrera a trabajar en eso, ¿te gustaría hacerlo?',
                ],
            ],
            7 => [
                'question_text' => 'Describe una ocasión en la que estabas trabajando en algo, un proyecto, una actividad, un pasatiempo, lo que sea, y perdiste por completo la noción del tiempo. ¿Qué estabas haciendo? ¿Qué lo hacía tan absorbente?',
                'conversation_prompt' => 'Quiero cambiar un poco y pensar en la energía. ¿Puedes describir una ocasión en la que estabas trabajando en algo, cualquier cosa, y perdiste por completo la noción del tiempo? ¿Qué estabas haciendo y qué lo hacía tan absorbente?',
                'follow_up_prompts' => [
                    '¿Era la actividad en sí, las personas con las que estabas, o el resultado al que querías llegar lo que te atrapaba?',
                    '¿Te pasa seguido, o fue más bien una experiencia poco común?',
                ],
            ],
            8 => [
                'question_text' => 'Piensa en una clase, proyecto o actividad que realmente te llenó de energía en lugar de agotarte. ¿Qué estabas haciendo? ¿Qué la hacía diferente de otras cosas?',
                'conversation_prompt' => 'Piensa en una clase, proyecto o actividad que realmente te llenó de energía, una de esas experiencias de las que sales con más energía de la que tenías al entrar. ¿Qué estabas haciendo y qué la hacía diferente?',
                'follow_up_prompts' => [
                    '¿Qué te daba energía exactamente? ¿La creatividad, resolver problemas, las personas, el resultado tangible?',
                    '¿Cómo se compara eso con las cosas que normalmente te drenan?',
                ],
            ],
            9 => [
                'question_text' => '¿Qué es algo que has hecho, construido, organizado o logrado y de lo que te sientes genuinamente orgulloso? Describe qué hiciste y por qué fue importante para ti.',
                'conversation_prompt' => '¿Qué es algo que has hecho, construido, organizado o logrado y de lo que te sientes genuinamente orgulloso? Cuéntame qué hiciste y por qué fue importante para ti.',
                'follow_up_prompts' => [
                    '¿Ese orgullo tenía más que ver con el proceso de hacerlo o con el resultado final?',
                    '¿Alguien más lo reconoció, o fue más bien un logro que valoraste en privado?',
                ],
            ],
            10 => [
                'question_text' => 'Si tuvieras un día completamente libre, sin obligaciones, y pudieras dedicarte a lo que quisieras, ¿qué elegirías hacer? Sé específico con la actividad; no solo "descansar" o "salir".',
                'conversation_prompt' => 'Si tuvieras un día completamente libre, sin obligaciones, y pudieras dedicarte a lo que quisieras, ¿qué elegirías? Busco algo específico, no solo "descansar" o "pasarla bien".',
                'follow_up_prompts' => [
                    '¿Qué es lo que te atrae de esa actividad en particular?',
                    '¿Crees que eso dice algo sobre el tipo de trabajo para el que estás hecho?',
                ],
            ],
            11 => [
                'question_text' => 'Describe una ocasión en la que tuviste que elegir entre dos cosas que te importaban de verdad, quizá entre estudiar para un examen y ayudar a un amigo, entre lo que tus padres querían y lo que tú sentías que era correcto, o entre algo seguro y algo arriesgado. ¿Qué elegiste y por qué?',
                'conversation_prompt' => 'Ahora quiero preguntarte sobre tus valores. Describe una ocasión en la que tuviste que elegir entre dos cosas que realmente te importaban, quizá entre ayudar a alguien y cumplir con tus propias obligaciones, o entre seguridad y riesgo. ¿Qué elegiste y por qué?',
                'follow_up_prompts' => [
                    'Mirando atrás, ¿sientes que tomaste la decisión correcta?',
                    '¿Qué dice esa decisión sobre lo que más valoras?',
                ],
            ],
            12 => [
                'question_text' => 'Piensa en una decisión que tomaste y que tus amigos o tu familia realmente no entendieron ni apoyaron. ¿Cuál fue y por qué decidiste hacerlo de todos modos?',
                'conversation_prompt' => 'Piensa en una decisión que tomaste y que la gente a tu alrededor, amigos o familia, realmente no entendió ni apoyó. ¿Cuál fue y por qué decidiste hacerlo de todos modos?',
                'follow_up_prompts' => [
                    '¿Fue difícil ir en contra de sus expectativas?',
                    '¿Qué te dio la confianza para mantenerte firme en tu decisión?',
                ],
            ],
            13 => [
                'question_text' => '¿Alguna vez has tenido que elegir entre hacer lo que otros esperaban de ti y hacer lo que realmente sentías que debías hacer? Describe qué pasó y cómo tomaste la decisión.',
                'conversation_prompt' => '¿Alguna vez has tenido que elegir entre lo que otros esperaban de ti y lo que realmente sentías que debías hacer? Cuéntame qué pasó y cómo lo resolviste.',
                'follow_up_prompts' => [
                    '¿Sueles seguir las expectativas o tu propio sentido de dirección?',
                    '¿Cómo piensas sobre el deber frente al llamado personal?',
                ],
            ],
            14 => [
                'question_text' => 'Describe una ocasión en la que algo que realmente querías no salió bien, quizá no fuiste aceptado en algún lugar, no quedaste en un equipo o fallaste en algo importante. ¿Cómo respondiste? ¿Qué hiciste después?',
                'conversation_prompt' => 'Quiero preguntarte ahora sobre algo más difícil. Describe una ocasión en la que algo que realmente querías no salió bien, quizá no fuiste aceptado en algún lugar o fallaste en algo importante. ¿Cómo respondiste y qué hiciste después?',
                'follow_up_prompts' => [
                    'Mirando atrás, ¿ves esa experiencia de manera distinta a como la veías en ese momento?',
                    '¿Esa puerta cerrada terminó señalándote otro camino?',
                ],
            ],
            15 => [
                'question_text' => '¿Qué es algo que limita lo que puedes hacer ahora mismo, quizá el dinero, el lugar donde vives, tus calificaciones, tu situación familiar o algo más? ¿Cómo piensas en esa limitación? ¿La ves como algo que debes superar, o te ayuda a entender mejor el camino que debes tomar?',
                'conversation_prompt' => '¿Qué es algo que limita lo que puedes hacer ahora mismo, dinero, lugar, familia, calificaciones, lo que sea? ¿Cómo piensas en esa limitación? ¿Es algo que debes superar, o te ayuda a ver tu camino con más claridad?',
                'follow_up_prompts' => [
                    '¿Crees que Dios usa las limitaciones para guiar a las personas?',
                    '¿Esa limitación ha influido en lo que piensas que estás llamado a hacer?',
                ],
            ],
            16 => [
                'question_text' => 'Imagínate a los 40 años y que alguien te pregunta: "¿A qué te dedicas?". ¿Cómo te gustaría responder? ¿Qué tipo de trabajo esperas estar haciendo y por qué sería importante?',
                'conversation_prompt' => 'Pensemos hacia adelante. Imagínate a los 40 años y que alguien te pregunta: "¿A qué te dedicas?". ¿Cómo te gustaría responder? ¿Qué tipo de trabajo esperas estar haciendo y por qué sería importante?',
                'follow_up_prompts' => [
                    '¿Ese "por qué importa" tiene más que ver con las personas a las que ayudas, con lo que construyes o con la vida que llevas?',
                    '¿Qué haría que ese trabajo se sintiera como un llamado y no solo como un empleo?',
                ],
            ],
            17 => [
                'question_text' => 'Si pudieras dedicar tu carrera a avanzar de verdad en la solución de un problema específico del mundo, no solo hablar de él, ¿qué problema elegirías? ¿Por qué ese?',
                'conversation_prompt' => 'Si pudieras dedicar tu carrera a avanzar de verdad en la solución de un problema específico, no solo hablar de él sino hacer algo real, ¿qué problema elegirías y por qué ese?',
                'follow_up_prompts' => [
                    '¿Eso está relacionado con algo que has vivido personalmente o con algo que has observado?',
                    '¿Qué tipo de papel te imaginas desempeñando para resolverlo?',
                ],
            ],
            18 => [
                'question_text' => 'Piensa en el tipo de impacto que quieres que tenga tu vida. Cuando seas mayor, ¿qué te gustaría que la gente dijera sobre cómo tu trabajo los afectó o mejoró las cosas?',
                'conversation_prompt' => 'Piensa en el impacto que quieres que tenga tu vida. Cuando seas mayor, ¿qué te gustaría que la gente dijera sobre cómo tu trabajo los afectó o mejoró las cosas?',
                'follow_up_prompts' => [
                    '¿Eso tiene más que ver con un impacto amplio en muchas personas o con un impacto profundo en unas pocas?',
                    '¿Cómo influye la fe en la manera en que imaginas ese impacto?',
                ],
            ],
            19 => [
                'question_text' => '¿En qué eres realmente bueno? No en lo que te gustaría ser bueno, sino en aquello para lo que la gente te busca. ¿Qué dicen tus profesores, amigos o familia que haces bien? Da ejemplos específicos.',
                'conversation_prompt' => 'Ya casi terminamos. ¿En qué eres realmente bueno, no en lo que te gustaría ser bueno, sino en aquello para lo que la gente te busca? ¿Qué dicen otros que haces bien? Dame ejemplos concretos.',
                'follow_up_prompts' => [
                    '¿Disfrutas esas cosas o simplemente te salen con facilidad?',
                    '¿Hay una diferencia entre aquello en lo que eres bueno y lo que realmente quieres hacer?',
                ],
            ],
            20 => [
                'question_text' => 'En este momento, ¿qué carreras o caminos profesionales estás considerando, aunque todavía no estés seguro? ¿Qué te atrae de esas opciones y qué te hace dudar de ellas?',
                'conversation_prompt' => 'Última pregunta. ¿Qué carreras o caminos profesionales estás considerando en este momento, aunque todavía no estés seguro? ¿Qué te atrae de esas opciones y qué te hace dudar?',
                'follow_up_prompts' => [
                    '¿Cuál de esas opciones se siente más como algo auténticamente tuyo y no solo como lo que otros esperan?',
                    'Si el dinero y las expectativas no fueran un factor, ¿cambiaría tu respuesta?',
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{question_text:string, conversation_prompt:string, follow_up_prompts: array<int, string>}>
     */
    protected function portugueseTranslations(): array
    {
        return [
            1 => [
                'question_text' => 'Pense em uma vez em que você ajudou alguém e isso realmente pareceu significativo, não apenas porque você deveria, mas porque de fato quis fazer isso. O que você fez? O que fez você querer ajudar daquela forma?',
                'conversation_prompt' => 'Quero começar pensando em uma vez em que você ajudou alguém, não porque precisava, mas porque realmente quis. Você pode me contar sobre isso? O que você fez e o que fez você querer ajudar daquela forma?',
                'follow_up_prompts' => [
                    'O que havia naquela situação específica que puxou você para perto?',
                    'Como você se sentiu enquanto ajudava? Houve algum momento que ficou marcado?',
                    'Você diria que esse tipo de ajuda é algo que aparece com frequência na sua vida?',
                ],
            ],
            2 => [
                'question_text' => 'Imagine que um amigo procura você com um problema. Que tipo de problema faria você pensar: "Eu realmente quero ajudar com isso"? Descreva a situação e o que você naturalmente teria vontade de fazer para ajudar.',
                'conversation_prompt' => 'Agora imagine que um amigo chega até você com um problema. Que tipo de problema faria você pensar sinceramente: "Eu quero ajudar com isso"? Descreva como seria essa situação e o que você naturalmente faria para ajudar.',
                'follow_up_prompts' => [
                    'O que esse tipo de problema tem que dá energia a você em vez de esgotar?',
                    'Você costuma entrar logo com ajuda prática ou primeiro tende a ouvir e entender melhor?',
                ],
            ],
            3 => [
                'question_text' => 'Quando você pensa em fazer diferença na vida de alguém, como isso se parece? Descreva uma forma específica de como você gostaria de ajudar pessoas, com o máximo de detalhes possível.',
                'conversation_prompt' => 'Quando você imagina fazer uma diferença real na vida de alguém, como isso se parece para você? Seja o mais específico e detalhado possível.',
                'follow_up_prompts' => [
                    'Isso tem mais a ver com causar impacto profundo em uma pessoa ou você se imagina ajudando muitas ao mesmo tempo?',
                    'O que faria você sentir que realmente causou essa diferença?',
                ],
            ],
            4 => [
                'question_text' => 'Qual é uma coisa no mundo, grande ou pequena, que realmente incomoda ou frustra você? Pode ser algo na sua escola, na sua comunidade ou na sociedade em geral. O que é isso e por que mexe tanto com você?',
                'conversation_prompt' => 'Quero fazer uma pergunta diferente. Qual é uma coisa no mundo, grande ou pequena, que realmente incomoda ou frustra você? Pode ser na sua escola, na sua comunidade ou no mundo em geral. O que é isso e por que isso mexe tanto com você?',
                'follow_up_prompts' => [
                    'Quando você pensa nisso, sente vontade de consertar a situação ou é mais uma coisa que você simplesmente não consegue deixar de notar?',
                    'Essa frustração já levou você a tentar fazer algo a respeito?',
                ],
            ],
            5 => [
                'question_text' => 'Se você pudesse consertar uma única coisa sobre como o mundo funciona, o que seria? Descreva o que está quebrado e como você gostaria que fosse diferente.',
                'conversation_prompt' => 'Se você pudesse consertar uma única coisa em como o mundo funciona, o que escolheria? Conte o que parece quebrado para você e como gostaria que fosse no lugar disso.',
                'follow_up_prompts' => [
                    'O que faz você sentir que é o tipo de pessoa que deveria trabalhar nesse problema?',
                    'Isso é algo que você sente há muito tempo ou é algo mais recente?',
                ],
            ],
            6 => [
                'question_text' => 'Pense em um problema que você percebeu e que a maioria das pessoas parece ignorar ou aceitar como normal, mas no qual você não consegue parar de pensar. O que é e por que você não consegue deixar isso de lado?',
                'conversation_prompt' => 'Agora pense em algo que a maioria das pessoas parece ignorar ou aceitar como "simplesmente é assim", mas sobre o qual você não consegue parar de pensar. O que é isso e por que você não consegue deixar para lá?',
                'follow_up_prompts' => [
                    'Você sente que enxerga esse problema de forma diferente da maioria das pessoas?',
                    'Se pudesse passar sua carreira trabalhando nisso, você gostaria?',
                ],
            ],
            7 => [
                'question_text' => 'Descreva uma vez em que você estava trabalhando em algo, um projeto, atividade, hobby, qualquer coisa, e perdeu completamente a noção do tempo. O que estava fazendo? O que tornou aquilo tão envolvente?',
                'conversation_prompt' => 'Quero mudar um pouco e pensar em energia. Você consegue descrever uma vez em que estava trabalhando em algo, qualquer coisa, e perdeu completamente a noção do tempo? O que estava fazendo e o que tornou aquilo tão envolvente?',
                'follow_up_prompts' => [
                    'Era a atividade em si, as pessoas com quem você estava ou o resultado que estava buscando que prendia você?',
                    'Isso acontece com frequência ou foi uma experiência mais rara?',
                ],
            ],
            8 => [
                'question_text' => 'Pense em uma aula, projeto ou atividade que realmente deu energia a você em vez de esgotar. O que você estava fazendo? O que fazia aquilo parecer diferente das outras coisas?',
                'conversation_prompt' => 'Pense em uma aula, projeto ou atividade que realmente deu energia a você, uma experiência da qual você saiu com mais energia do que tinha quando entrou. O que você estava fazendo e o que tornava aquilo diferente?',
                'follow_up_prompts' => [
                    'O que exatamente dava energia a você? A criatividade, resolver problemas, as pessoas, o resultado concreto?',
                    'Como isso se compara com as coisas que normalmente drenam você?',
                ],
            ],
            9 => [
                'question_text' => 'O que é algo que você fez, construiu, organizou ou realizou e do qual sente orgulho de verdade? Descreva o que você fez e por que isso foi importante para você.',
                'conversation_prompt' => 'O que é algo que você fez, construiu, organizou ou realizou e do qual sente orgulho de verdade? Conte o que você fez e por que isso foi importante para você.',
                'follow_up_prompts' => [
                    'Esse orgulho tinha mais a ver com o processo de fazer aquilo ou com o resultado final?',
                    'Outras pessoas reconheceram isso ou foi mais uma conquista importante para você em particular?',
                ],
            ],
            10 => [
                'question_text' => 'Se você tivesse um dia completamente livre, sem obrigações, e pudesse se dedicar ao que quisesse, o que escolheria fazer? Seja específico sobre a atividade, não apenas "descansar" ou "sair".',
                'conversation_prompt' => 'Se você tivesse um dia completamente livre, sem obrigações, e pudesse se dedicar ao que quisesse, o que escolheria? Estou procurando algo específico, não apenas "descansar" ou "curtir".',
                'follow_up_prompts' => [
                    'O que atrai você nessa atividade específica?',
                    'Você acha que isso diz algo sobre o tipo de trabalho para o qual foi feito?',
                ],
            ],
            11 => [
                'question_text' => 'Descreva uma vez em que você teve de escolher entre duas coisas com as quais realmente se importava, talvez entre estudar para uma prova e ajudar um amigo, entre o que seus pais queriam e o que você sentia ser certo, ou entre algo seguro e algo arriscado. O que você escolheu e por quê?',
                'conversation_prompt' => 'Agora quero perguntar sobre valores. Descreva uma vez em que você teve de escolher entre duas coisas com as quais realmente se importava, talvez entre ajudar alguém e cumprir suas próprias obrigações, ou entre segurança e risco. O que você escolheu e por quê?',
                'follow_up_prompts' => [
                    'Olhando para trás, você sente que tomou a decisão certa?',
                    'O que essa decisão revela sobre aquilo que você mais valoriza?',
                ],
            ],
            12 => [
                'question_text' => 'Pense em uma decisão que você tomou e que seus amigos ou sua família não entenderam nem apoiaram muito bem. Qual foi essa decisão e por que você decidiu seguir em frente mesmo assim?',
                'conversation_prompt' => 'Pense em uma decisão que você tomou e que as pessoas ao seu redor, amigos ou família, não entenderam ou apoiaram muito bem. Qual foi essa decisão e por que você escolheu seguir em frente mesmo assim?',
                'follow_up_prompts' => [
                    'Foi difícil ir contra as expectativas deles?',
                    'O que deu a você confiança para permanecer firme na sua decisão?',
                ],
            ],
            13 => [
                'question_text' => 'Você já teve de escolher entre fazer o que os outros esperavam de você e fazer o que realmente sentia que deveria fazer? Descreva o que aconteceu e como tomou a decisão.',
                'conversation_prompt' => 'Você já teve de escolher entre o que os outros esperavam de você e aquilo que realmente sentia que deveria fazer? Conte o que aconteceu e como você trabalhou essa decisão.',
                'follow_up_prompts' => [
                    'Você costuma seguir as expectativas ou o seu próprio senso de direção?',
                    'Como você pensa sobre dever e chamado pessoal?',
                ],
            ],
            14 => [
                'question_text' => 'Descreva uma vez em que algo que você realmente queria não deu certo, talvez você não tenha sido aceito em algum lugar, não tenha entrado em um time ou tenha falhado em algo importante. Como você reagiu? O que fez depois?',
                'conversation_prompt' => 'Quero perguntar agora sobre algo mais difícil. Descreva uma vez em que algo que você realmente queria não deu certo, talvez você não tenha sido aceito em algum lugar ou tenha falhado em algo importante. Como você reagiu e o que fez depois?',
                'follow_up_prompts' => [
                    'Olhando para trás, você enxerga essa experiência de maneira diferente da que enxergava na época?',
                    'Essa porta fechada acabou apontando você para outro caminho?',
                ],
            ],
            15 => [
                'question_text' => 'O que é algo que limita o que você pode fazer agora, talvez dinheiro, lugar onde mora, notas, situação familiar ou outra coisa? Como você pensa sobre essa limitação? Parece algo que você precisa superar, ou isso ajuda você a entender melhor qual caminho deve seguir?',
                'conversation_prompt' => 'O que é algo que limita o que você pode fazer agora, dinheiro, lugar, família, notas, qualquer coisa? Como você pensa sobre essa limitação? É algo a superar ou isso ajuda você a enxergar seu caminho com mais clareza?',
                'follow_up_prompts' => [
                    'Você acredita que Deus usa limitações para guiar as pessoas?',
                    'Essa limitação influenciou aquilo que você pensa que é chamado para fazer?',
                ],
            ],
            16 => [
                'question_text' => 'Imagine que você tem 40 anos e alguém pergunta: "O que você faz?". Como você espera responder? Que tipo de trabalho espera estar fazendo e por que isso seria importante?',
                'conversation_prompt' => 'Vamos pensar no futuro. Imagine que você tem 40 anos e alguém pergunta: "O que você faz?". Como você espera responder? Que tipo de trabalho espera estar fazendo e por que isso seria importante?',
                'follow_up_prompts' => [
                    'Esse "por que isso importa" tem mais a ver com as pessoas que você ajuda, com aquilo que você constrói ou com a vida que você vive?',
                    'O que faria esse trabalho parecer um chamado e não apenas um emprego?',
                ],
            ],
            17 => [
                'question_text' => 'Se você pudesse dedicar sua carreira a realmente avançar na solução de um problema específico no mundo, não apenas falar sobre ele, qual problema escolheria? Por que esse?',
                'conversation_prompt' => 'Se você pudesse dedicar sua carreira a realmente avançar na solução de um problema específico, não apenas falar sobre ele, mas fazer algo concreto, qual problema escolheria e por que esse?',
                'follow_up_prompts' => [
                    'Isso está relacionado a algo que você viveu pessoalmente ou a algo que observou?',
                    'Que tipo de papel você se imagina desempenhando para ajudar a resolver isso?',
                ],
            ],
            18 => [
                'question_text' => 'Pense no tipo de impacto que você quer que sua vida tenha. Quando for mais velho, o que gostaria que as pessoas dissessem sobre como o seu trabalho afetou a vida delas ou melhorou as coisas?',
                'conversation_prompt' => 'Pense no impacto que você quer que sua vida tenha. Quando for mais velho, o que gostaria que as pessoas dissessem sobre como o seu trabalho afetou a vida delas ou melhorou as coisas?',
                'follow_up_prompts' => [
                    'Isso tem mais a ver com um impacto amplo em muitas pessoas ou com um impacto profundo em poucas?',
                    'Como a fé molda a maneira como você imagina esse impacto?',
                ],
            ],
            19 => [
                'question_text' => 'Em que você é realmente bom? Não aquilo em que gostaria de ser bom, mas aquilo para o qual as pessoas procuram você. O que professores, amigos ou familiares dizem que você faz bem? Dê exemplos específicos.',
                'conversation_prompt' => 'Estamos quase terminando. Em que você é realmente bom, não aquilo em que gostaria de ser bom, mas aquilo para o qual as pessoas procuram você? O que os outros dizem que você faz bem? Dê exemplos concretos.',
                'follow_up_prompts' => [
                    'Você gosta dessas coisas ou elas simplesmente vêm com facilidade para você?',
                    'Existe uma diferença entre aquilo em que você é bom e o que realmente quer fazer?',
                ],
            ],
            20 => [
                'question_text' => 'Neste momento, quais cursos ou caminhos profissionais você está considerando, mesmo que ainda não tenha certeza? O que atrai você nessas opções e o que faz você hesitar em relação a elas?',
                'conversation_prompt' => 'Última pergunta. Quais cursos ou caminhos profissionais você está considerando neste momento, mesmo que ainda não tenha certeza? O que atrai você nessas opções e o que faz você hesitar?',
                'follow_up_prompts' => [
                    'Qual dessas opções parece mais autenticamente sua, e não apenas aquilo que os outros esperam?',
                    'Se dinheiro e expectativas não fossem um fator, sua resposta mudaria?',
                ],
            ],
        ];
    }
}
