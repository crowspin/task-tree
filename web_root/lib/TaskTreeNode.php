<?php

class TaskTreeNode {
    public string $id;
    public array $children;
    public array $row;

    function __construct($row){
        $this->id = $row["id"];
        $this->children = [];
        $this->row = $row;
    }

    public function first(){
        foreach ($this->children as &$child){
            if (!$child->row["is_group"]) return $child->id;
            if (count($child->children) == 0) continue;
            $result = $child->first();
            if ($result) return $result;
        }
        return false;
    }

    public function find($id){
        if ($this->id == $id) return true;
        else if (count($this->children) > 0){
            foreach ($this->children as $child){
                if ($child->find($id)) return true;
            }
        }
        return false;
    }

    public function generate_html_sidebar($current_list){
        $top = "<li class='header'>In Progress</li>";
        $divider = "<li class='midline header'>Completed</li>";
        $bottom = "";
        $incomplete = "";
        $complete = "";

        for ($i = 0; $i < count($this->children); $i++){
            [$inc, $com] = $this->children[$i]->generate_html_sidebar_r($current_list);
            $incomplete .= $inc;
            $complete .= $com;
        }

        if (strlen($complete) > 0) return $top . $incomplete . $divider . $complete . $bottom;
        else return $top . $incomplete;
    }

    private function generate_html_sidebar_r($current_list){
        $INCOMPLETE = "";
        $COMPLETE = "";

        $this_li = "<li" . (($this->id == $current_list)?" class='active'":"") . "><img><a href='?pg=" . $this->id . "'>" . $this->row["text"] . "</a></li>";

        if ($this->row["is_group"]){
            if (count($this->children) == 0){
                $COMPLETE .= $this_li;
            } else {
                $INC = "";
                $COM = "";
                for ($i = 0; $i < count($this->children); $i++){
                    [$inc, $com] = $this->children[$i]->generate_html_sidebar_r($current_list);
                    $INC .= $inc;
                    $COM .= $com;
                }
                if (strlen($INC) > 0) {
                    $INCOMPLETE .= $this_li . "<group>" . $INC . "</group>";
                }
                if (strlen($COM) > 0){
                    $COMPLETE .= $this_li . "<group>" . $COM . "</group>";
                }
            }
        } else {
            if ($this->row["complete"]) $COMPLETE .= $this_li;
            else $INCOMPLETE .= $this_li;
        }

        return [$INCOMPLETE, $COMPLETE];
    }

    public function generate_html_tasklist(): string {
        if (count($_SESSION["reverseNav"]) > 1){
            [$id, $text] = $_SESSION["reverseNav"][count($_SESSION["reverseNav"])-2];
            $reverseNavLink = "<a href=\"?pg=$id\">&lt; Back to $text</a>";
        } else $reverseNavLink = "";
        
        $headblock = "$reverseNavLink<div>";
        if (!$this->row["is_group"]) $headblock .= "<input type=checkbox" . (($this->row["complete"])?" checked":"") . " onclick=\"location.href='modify.php?id=" . $this->id . "&action=toggleComplete" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'\"id=\"toggleComplete['" . $this->id . "']\"/>";
        $headblock .= "<h1>" . $this->row["text"] . "</h1>";
        $headblock .= "<a href='modify.php?id=" . $this->id . "&action=addChild" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>+</a>";
        if ($this->id != "0") $headblock .= "<a href='modify.php?id=" . $this->id . "&action=edit" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>...</a>";
        $headblock .= "</div>";

        $divider = "<div class='midline'><h2>Completed</h2></div>";

        $incomplete = "";
        $complete = "";

        for ($i = 0; $i < count($this->children); $i++){
            [$inc, $com] = $this->children[$i]->generate_html_tasklist_r();
            $incomplete .= $inc;
            $complete .= $com;
        }
        
        if (strlen($complete) > 0) return $headblock . $incomplete . $divider . $complete;
        else return $headblock . $incomplete;
    }

    private function generate_html_tasklist_r(): array {
        $INCOMPLETE = "";
        $COMPLETE = "";
        if ($this->row["is_group"]){
            $INC = "";
            $COM = "";
            for ($i = 0; $i < count($this->children); $i++){
                [$inc, $com] = $this->children[$i]->generate_html_tasklist_r();
                $INC .= $inc;
                $COM .= $com;
            }
            $grouphead = "<group><div><h3 onclick=\"location.href='?pg=" . $this->id . "'\">" . $this->row["text"] . "</h3><a href='modify.php?id=" . $this->id . "&action=addChild" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>+</a><a href='modify.php?id=" . $this->id . "&action=edit" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>...</a></div>";
            $groupfoot = "</group>";
            if (strlen($INC) > 0){
                $INCOMPLETE .= $grouphead . $INC . $groupfoot;
            }
            if (strlen($COM) > 0 || count($this->children) == 0){
                $COMPLETE .= $grouphead . $COM . $groupfoot;
            }
        } else {
            $p1 = "<li><input type=checkbox";
            $p2 = " onclick=\"location.href='modify.php?id=" . $this->id . "&action=toggleComplete" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'\"id=\"toggleComplete['" . $this->id . "']\"/><p onclick=\"location.href='?pg=" . $this->id . "'\">" . $this->row["text"] . "</p><a href='modify.php?id=" . $this->id . "&action=edit" . ((isset($_GET["pg"]) && $_GET["pg"] != "")?"&returnTo=" . $_GET["pg"]:"") . "'>...</a></li>";
            if ($this->row["complete"]){
                $COMPLETE .= $p1 . " checked" . $p2;
            } else {
                $INCOMPLETE .= $p1 . $p2;
            }
        }
        return [$INCOMPLETE, $COMPLETE];
    }
}