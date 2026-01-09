<?php

namespace SPTK;

enum Action {

  case COPY;
  case CUT;
  case PASTE;

  case MOVE_LEFT;
  case MOVE_RIGHT;
  case MOVE_UP;
  case MOVE_DOWN;
  case MOVE_FIRST;
  case MOVE_LAST;
  case MOVE_START;
  case MOVE_END;

  case PAGE_UP;
  case PAGE_DOWN;

  case LEVEL_UP;
  case LEVEL_DOWN;

  case SELECT_LEFT;
  case SELECT_RIGHT;
  case SELECT_UP;
  case SELECT_DOWN;
  case SELECT_FIRST;
  case SELECT_LAST;
  case SELECT_START;
  case SELECT_END;
  case SELECT_ITEM;
  case SELECT_PAGE_UP;
  case SELECT_PAGE_DOWN;
  case SELECT_LEVEL_UP;
  case SELECT_LEVEL_DOWN;

  case DELETE_FORWARD;
  case DELETE_BACK;

  case UNDO;
  case REDO;

  case CLOSE;
  case DO_IT;

  case SWITCH_LEFT;
  case SWITCH_RIGHT;
  case SWITCH_UP;
  case SWITCH_DOWN;
  case SWITCH_NEXT;
  case SWITCH_PREVIOUS;

}
