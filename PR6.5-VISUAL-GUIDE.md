# PR 6.5 - Visual Architecture Guide

## System Overview Diagram

```
┌─────────────────────────────────────────────────────────────────────┐
│                         HOMA EVENT BUS                               │
│                      (window.Homa)                                   │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    GLOBAL STATE                              │   │
│  │  • isSidebarOpen: boolean                                   │   │
│  │  • currentUserInput: object                                 │   │
│  │  • pageMap: object                                          │   │
│  │  • indexerReady: boolean                                    │   │
│  │  • aiProcessing: boolean                                    │   │
│  └─────────────────────────────────────────────────────────────┘   │
│                                                                       │
│  ┌─────────────────────────────────────────────────────────────┐   │
│  │                    EVENT METHODS                             │   │
│  │  • emit(eventName, data)                                    │   │
│  │  • on(eventName, callback) → cleanup()                      │   │
│  │  • off(eventName, callback)                                 │   │
│  │  • updateState(updates)                                     │   │
│  │  • getState() → state                                       │   │
│  │  • checkConnectivity() → health                             │   │
│  └─────────────────────────────────────────────────────────────┘   │
└────────────────────┬──────────────────┬─────────────────┬───────────┘
                     │                  │                 │
          ┌──────────▼─────────┐ ┌─────▼─────────┐ ┌────▼──────────┐
          │    INDEXER         │ │ ORCHESTRATOR  │ │  COMMAND      │
          │  (Vanilla JS)      │ │ (Vanilla JS)  │ │  INTERPRETER  │
          └──────────┬─────────┘ └─────┬─────────┘ └────┬──────────┘
                     │                  │                 │
                     │                  │                 │
          Emits:     │       Emits:     │      Listens:  │
          • indexer:ready      • sidebar:opened  • ai:command
          • site:input_change  • sidebar:closed  • ai:response_received
                     │                  │                 │
                     └──────────┬───────┴─────────────────┘
                                │
                     ┌──────────▼──────────────┐
                     │    REACT SIDEBAR        │
                     │    (React 18)           │
                     │                         │
                     │  Uses:                  │
                     │  • useHomaEvent()       │
                     │  • useHomaEmit()        │
                     │  • useHomaState()       │
                     │                         │
                     │  Emits:                 │
                     │  • react:ready          │
                     │  • chat:user_message    │
                     │  • ai:processing        │
                     │                         │
                     │  Listens:               │
                     │  • site:input_change    │
                     │  • ai:response_received │
                     └─────────────────────────┘
```

## Event Flow Examples

### Example 1: User Changes Form Field

```
┌─────────────┐
│    USER     │
│   TYPES     │
│  IN FORM    │
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│   HOMA INDEXER      │
│  (Change Listener)  │
│  Debounce: 300ms    │
└──────┬──────────────┘
       │
       │ emit('site:input_change', {
       │   field: 'tirage',
       │   value: '500',
       │   meaning: 'تیراژ'
       │ })
       │
       ▼
┌─────────────────────┐
│   EVENT BUS         │
│  Broadcast to all   │
└──┬────────┬─────┬───┘
   │        │     │
   ▼        ▼     ▼
┌────┐  ┌────┐  ┌────────┐
│React│  │AI  │  │Custom  │
│Side │  │Log │  │Scripts │
│bar  │  │ger │  │        │
└────┘  └────┘  └────────┘
```

### Example 2: AI Sends Command

```
┌─────────────┐
│  GEMINI AI  │
│  Generates  │
│   Command   │
└──────┬──────┘
       │
       ▼
┌─────────────────────┐
│  REACT SIDEBAR      │
│  Receives Response  │
└──────┬──────────────┘
       │
       │ emit('ai:command', {
       │   command: 'HIGHLIGHT',
       │   target_selector: '.button'
       │ })
       │
       ▼
┌─────────────────────┐
│   EVENT BUS         │
└──────┬──────────────┘
       │
       ▼
┌─────────────────────┐
│ COMMAND INTERPRETER │
│  Parse & Execute    │
└──────┬──────────────┘
       │
       ├──→ Find element
       ├──→ Add highlight class
       └──→ Scroll into view
```

### Example 3: Sidebar Opens

```
┌─────────────┐
│    USER     │
│   CLICKS    │
│  FAB BUTTON │
└──────┬──────┘
       │
       ▼
┌──────────────────────┐
│  HOMA ORCHESTRATOR   │
│  openSidebar()       │
└──────┬───────────────┘
       │
       ├──→ Add CSS class 'homa-open'
       │
       ├──→ updateState({ isSidebarOpen: true })
       │
       └──→ emit('sidebar:opened')
              │
              ▼
       ┌──────────────────┐
       │   EVENT BUS      │
       └──────┬───────────┘
              │
      ┌───────┴───────┬──────────┬────────┐
      ▼               ▼          ▼        ▼
┌─────────┐    ┌─────────┐  ┌──────┐  ┌──────┐
│ React   │    │ Indexer │  │ CSS  │  │Custom│
│ Updates │    │ Notified│  │Anim  │  │Code  │
└─────────┘    └─────────┘  └──────┘  └──────┘
```

## Script Loading Order

```
Priority 5:  ┌──────────────────────┐
             │  homa-event-bus.js   │  ← Loads FIRST
             │  (window.Homa)       │
             └──────────┬───────────┘
                        │
Priority 5:  ┌──────────▼───────────┐
             │ homa-command-        │
             │ interpreter.js       │
             └──────────┬───────────┘
                        │
Priority 20: ┌──────────▼───────────┐
             │ homa-indexer.js      │  ← Depends on Event Bus
             │ homa-input-observer  │
             │ homa-spatial-nav     │
             └──────────┬───────────┘
                        │
Priority 30: ┌──────────▼───────────┐
             │ homa-orchestrator.js │  ← Depends on Event Bus
             │ homa-fab.js          │
             │ React + Sidebar      │
             └──────────────────────┘
```

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────┐
│                     FRONTEND                             │
│                                                          │
│  ┌──────────────┐        ┌──────────────┐              │
│  │   DIVI FORM  │───────▶│   INDEXER    │              │
│  │ (Shortcodes) │ change │  (Listeners) │              │
│  └──────────────┘        └──────┬───────┘              │
│                                  │                       │
│                                  │ emit                  │
│                                  ▼                       │
│                          ┌──────────────┐               │
│                          │  EVENT BUS   │               │
│                          │ (window.Homa)│               │
│                          └───┬──────┬───┘               │
│                              │      │                    │
│                      emit ◄──┤      └──► listen         │
│                              │                           │
│  ┌──────────────┐           │       ┌──────────────┐   │
│  │    REACT     │◄──────────┘       │   COMMAND    │   │
│  │   SIDEBAR    │                   │ INTERPRETER  │   │
│  └──────┬───────┘                   └──────┬───────┘   │
│         │                                   │            │
│         │ API Call                          │ Execute    │
│         ▼                                   ▼            │
└─────────┼───────────────────────────────────┼───────────┘
          │                                   │
          │ POST /ai/chat                     │ UI Actions
          ▼                                   ▼
┌─────────────────────────────────────────────────────────┐
│                      BACKEND                             │
│                                                          │
│  ┌──────────────┐        ┌──────────────┐              │
│  │ HT_Parallel  │───────▶│ HT_AI_       │              │
│  │ _UI          │  chat  │ Controller   │              │
│  └──────────────┘        └──────┬───────┘              │
│                                  │                       │
│                                  ▼                       │
│                          ┌──────────────┐               │
│                          │ HT_Gemini_   │               │
│                          │ Client       │               │
│                          └──────┬───────┘               │
│                                  │                       │
│                                  ▼                       │
│                          ┌──────────────┐               │
│                          │  GEMINI API  │               │
│                          └──────────────┘               │
└─────────────────────────────────────────────────────────┘
```

## Component Interaction Matrix

| Component | Emits | Listens | Updates State |
|-----------|-------|---------|---------------|
| **Event Bus** | - | - | ✅ |
| **Indexer** | indexer:ready<br>site:input_change | - | ✅ pageMap |
| **Orchestrator** | sidebar:opened<br>sidebar:closed | - | ✅ isSidebarOpen |
| **Command Interpreter** | command:executed | ai:command<br>ai:response_received | - |
| **React Sidebar** | react:ready<br>chat:user_message<br>ai:processing | site:input_change<br>ai:response_received | - |
| **Custom Scripts** | Custom events | Custom events | Custom state |

## State Lifecycle

```
┌─────────────────┐
│ Page Load       │
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Event Bus Init  │  state = { isSidebarOpen: false, ... }
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Indexer Scans   │  emit('indexer:ready')
└────────┬────────┘  updateState({ indexerReady: true })
         │
         ▼
┌─────────────────┐
│ React Loads     │  emit('react:ready')
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ User Interacts  │  emit('site:input_change')
└────────┬────────┘  updateState({ currentUserInput: {...} })
         │
         ▼
┌─────────────────┐
│ AI Processes    │  emit('ai:processing', { processing: true })
└────────┬────────┘  updateState({ aiProcessing: true })
         │
         ▼
┌─────────────────┐
│ AI Responds     │  emit('ai:response_received')
└────────┬────────┘  emit('ai:command')
         │
         ▼
┌─────────────────┐
│ Command Exec    │  emit('command:executed')
└────────┬────────┘
         │
         ▼
┌─────────────────┐
│ Visual Feedback │  Highlight, Scroll, etc.
└─────────────────┘
```

## Memory Management

```
┌─────────────────┐
│ Create Listener │
└────────┬────────┘
         │
         │ const cleanup = Homa.on('event', callback)
         │
         ▼
┌─────────────────────────┐
│ Listener Registered     │
│ • Added to listeners Map│
│ • Native event listener │
└────────┬────────────────┘
         │
         │ Component active...
         │
         ▼
┌─────────────────────────┐
│ Component Unmounts      │
│ (or manual cleanup)     │
└────────┬────────────────┘
         │
         │ cleanup()
         │
         ▼
┌─────────────────────────┐
│ Listener Removed        │
│ • Removed from Map      │
│ • Native listener off   │
│ • Memory released       │
└─────────────────────────┘
```

## Performance Optimization

```
User Types → [Debounce 300ms] → Event Emitted
                                      ↓
Event Bus → [< 10ms latency] → Listeners Notified
                                      ↓
Listeners → [Async execution] → UI Updates
```

**Key Optimizations:**
- ✅ Debounced form listeners (300ms)
- ✅ Async command execution
- ✅ Event history limited (100 events)
- ✅ Cleanup functions prevent memory leaks
- ✅ No unnecessary re-renders in React

## Testing Workflow

```
┌──────────────────┐
│ Open WordPress   │
└────────┬─────────┘
         │
         ▼
┌──────────────────────────┐
│ Open Browser Console     │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│ Run Connectivity Check   │
│ Homa.checkConnectivity() │
└────────┬─────────────────┘
         │
         ├─→ All green? Continue
         └─→ Red? Debug scripts
                ↓
┌──────────────────────────┐
│ Test Event Flow          │
│ Homa.emit('test', {})    │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│ Test Form Changes        │
│ Type in form fields      │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│ Test AI Commands         │
│ Emit HIGHLIGHT, SCROLL   │
└────────┬─────────────────┘
         │
         ▼
┌──────────────────────────┐
│ Check Event History      │
│ Homa.getEventHistory()   │
└──────────────────────────┘
```

## Security Boundaries

```
┌─────────────────────────────────────┐
│         SECURE BOUNDARY             │
│                                     │
│  ┌─────────────────────────────┐  │
│  │      Event Bus              │  │
│  │  (No sensitive data)        │  │
│  └─────────────────────────────┘  │
│                                     │
│  Public Data Only:                  │
│  • Field names (not values)        │
│  • UI commands                      │
│  • State flags                      │
│  • Navigation events                │
└─────────────────────────────────────┘
         │
         │ API Call with Nonce
         ▼
┌─────────────────────────────────────┐
│         SECURE BACKEND              │
│                                     │
│  • Nonce verification              │
│  • User authentication             │
│  • Input sanitization              │
│  • Database protection             │
└─────────────────────────────────────┘
```

---

**Note**: All diagrams use ASCII art for compatibility. For production documentation, consider converting to Mermaid.js or similar tools for interactive diagrams.
