# **VOCATIONAL FINDER — DESIGN & INTERFACE SPEC**

*(Sophisticated. Intuitive. Quiet. Serious.)*

---

## **1\. DESIGN PHILOSOPHY (NON-NEGOTIABLE)**

### **Core principles**

* **Clarity over cleverness**  
* **Silence over stimulation**  
* **Curiosity over persuasion**  
* **Depth over speed**

If a design choice answers *“Would this make it feel more impressive?”* → reject it.  
If it answers *“Would this help someone pay attention?”* → keep it.

---

### **The desired feeling**

“This feels like someone thought carefully about me.”

Comparable *ethos* (not visuals):

* early Apple product pages  
* long-form essays  
* spiritual exercises  
* letters from a wise mentor

---

## **2\. VISUAL SYSTEM**

### **Color palette**

* Background: **Off-white**   
* Text: **Near-black**, not pure black  
* Accent: **One muted neutral** (used sparingly for dividers or emphasis)

No gradients.  
No shadows.  
No cards.

---

### **Typography**

**Two fonts only.**

1. **Primary body font**  
   * Serif or humanist serif  
   * Optimized for long reading  
   * Comfortable line height (1.6–1.8)  
2. **Secondary UI font**  
   * Clean sans-serif  
   * Used *only* for:  
     * buttons  
     * progress indicators  
     * navigation labels

Hierarchy matters more than size.  
Whitespace is part of the typography.

---

### **Layout**

* Single-column layout  
* Max width: **600–680px**  
* Generous vertical spacing  
* No sidebars  
* No floating elements

Everything should feel *centered and intentional*.

---

## **3\. SITE STRUCTURE (TOP-LEVEL)**

### **Pages / States**

1. **Landing / Threshold**  
2. **Orientation**  
3. **Assessment (20-question flow)**  
4. **Transition / Synthesis Pause**  
5. **Vocational Articulation (Output)**  
6. **Next Steps / Continuation**

No visible menu during the assessment.  
No distractions.

---

## **4\. PAGE-BY-PAGE SPEC**

---

## **PAGE 1 — LANDING / THRESHOLD**

### **Purpose**

Create **unease \+ trust**.  
This is not a CTA page. It’s a *threshold*.

---

### **Content structure**

* Short headline (1–2 lines)  
* 2–3 sentences of framing  
* One button

---

### **Copy tone (example style, not final text)**

* Declarative  
* Calm  
* Slightly unsettling (in a good way)

Example *pattern*:

Most people are taught to choose a career.  
Very few are taught to discern a calling.

Button:

Begin discernment →

No pricing.  
No testimonials.  
No promises.

---

## **PAGE 2 — ORIENTATION**

### **Purpose**

Give **permission** and set posture.

---

### **Content structure**

* Short paragraph explaining:  
  * This is not a test  
  * No right answers  
  * Takes time  
* Clear expectations:  
  * \~30–45 minutes  
  * Best done without distractions

---

### **Interface rules**

* No scrolling walls of text  
* Calm pacing  
* One “Continue” button

Optional (recommended):

* Checkbox:  
  “I’m willing to answer honestly, not impressively.”

This checkbox is symbolic — not functional.

---

## **PAGE 3 — ASSESSMENT FLOW (CORE EXPERIENCE)**

### **Structure**

* **One question per screen**  
* Same layout for all 20 questions

---

### **Screen layout**

1. Category label (small, subtle, optional)  
2. Question text (primary focus)  
3. Large text input  
4. “Continue” button

---

### **Text input rules**

* Multi-line  
* No character counter  
* Auto-expanding  
* Encouraging placeholder like:  
  “Take your time. Write freely.”

Autosave every keystroke (Bubble backend).

---

### **Navigation**

* Forward only (default)  
* Optional “Back” link (small, subtle)

No progress bar percentages.  
Instead:

Question 7 of 20

---

### **Critical design rule**

**The interface must never react to the content of the answer.**

No:

* “Great answer\!”  
* Checkmarks  
* Validation feedback

Neutrality preserves depth.

---

## **PAGE 4 — TRANSITION / SYNTHESIS PAUSE**

### **Purpose**

Create anticipation *without mechanics*.

---

### **Content**

* One paragraph  
* Calm, grounded language

Example *pattern*:

We’re now looking for patterns across what you shared —  
not isolated answers, but the story they tell together.

Button:

Continue →

No loading spinners.  
No “AI is analyzing your responses.”

---

## **PAGE 5 — VOCATIONAL ARTICULATION (OUTPUT)**

### **Purpose**

This is the **emotional center** of the product.

It should feel like:

* a letter  
* a vocational mirror  
* a discernment summary

---

### **Layout**

* Scrollable document  
* Clear section headers  
* No icons  
* No charts  
* No categories shown

---

### **Recommended structure**

1. **Opening synthesis paragraph**  
   * “Based on your responses…”  
2. **Vocational Orientation**  
   * Narrative articulation (like your architecture example)  
3. **Primary Vocational Pathways**  
   * Bullet list  
   * Specific, contextual, not generic  
4. **Specific Considerations**  
   * Explains *mode*, *trajectory*, *secondary orientations*  
5. **Next Steps**  
   * Sequenced  
   * Formation-oriented  
   * No urgency

---

### **Design rules**

* Text is king  
* Wide margins  
* Clear hierarchy  
* Reads well when printed

Optional:

* “Download as PDF”  
* “Email this to yourself”

---

## **PAGE 6 — CONTINUATION / RELEASE**

### **Purpose**

Let the user go **with dignity**.

---

### **Content**

* Short reflection  
* Invitation to continue discernment elsewhere:  
  * community  
  * mentors  
  * formation environments

Soft options only:

* Save results  
* Revisit later  
* Learn about next steps (cohorts, retreats, etc.)

No hard funnel here.

---

## **5\. BUBBLE.IO IMPLEMENTATION NOTES (FOR YOUR BUILDER)**

### **Architecture**

* One main “Assessment” page  
* Custom states for:  
  * current question index  
  * saved answers  
* Backend workflow:  
  * Send all answers at once for synthesis  
  * Store structured output sections

---

### **Avoid**

* Repeating groups for questions (use dynamic states instead)  
* Visible data fields  
* Client-side logic clutter

---

## **6\. WHAT MAKES THIS DIFFERENT (AND WHY IT WILL WORK)**

Most tools ask:

“How fast can we get a result?”

Yours asks:

“How carefully can we listen?”

This design:

* Honors the theology underneath  
* Prevents reductionism  
* Makes AI feel human without pretending it is  
* Positions you *above* career tests, not alongside them

