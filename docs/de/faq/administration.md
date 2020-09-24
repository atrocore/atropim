# Administration

  

## Kann man User-Interfaces aus dem Adminbereich anpassen?

Ja, es ist möglich, die Layouts von Listen- und Detailseiten direkt aus dem Adminbereich mithilfe des Layout Managers anzupassen. Dabei können Sie über Drag-and-Drop nicht nur die anzuzeigenden Felder bestimmen, sondern auch deren Reihenfolge und die Zuordnung zu einer Gruppe der Elemente.



## Kann man benutzerdefinierte Felder anlegen?

Ja, für jede Entität im System (d.h. Produkte, Attribute, Kategorien, Assoziationen, Produktfamilien, etc.) kann man benutzerdefinierte Felder anlegen. Nutzen Sie dafür den Entity Manager.

TreoPIM bietet dem Anwender viel mehr Möglichkeiten an, als nur benutzerdefinierte Felder anzulegen, denn TreoPIM hat ein vollkommen flexibles Datenmodell. Man kann neue Entitäten anlegen, bestehende Entitäten editieren, die Relationen zwischen den Entitäten anlegen und verändern sowie die Metadaten editieren.



## Kann der Nutzer die Berechtigungen einschränken?

Ja, TreoPIM hat ein sehr flexibles Zugriffs- und Berechtigungskonzept. Durch die Rollen wird bestimmt, welcher Nutzer was und bei welchen Entitäten machen kann. Es ist auch möglich, die Zugriffsebene der Nutzer nur für eigene Einträge, Einträge des eigenen Teams oder alle Einträge zu bestimmen.

  

## Kann man die Berechtigungen auf Feldebene editieren?

Ja, für jede Rolle und jeden Nutzer ist es möglich, die Berechtigungen auf Feldebene einzurichten, damit z.B. ein User zwar Einträge aus einer Entität sehen kann, aber ohne Werte für ein spezifisches Feld, z.B. Preis, interne Notizen etc.

Bei der Einrichtung der Berechtigungen auf Feldebene ist es möglich zu bestimmen, ob ein Nutzer das Feld sehen kann oder nicht bzw. ob er den Wert editieren kann.



## Was ist ACL Strict Mode?

ACL Strict Mode bestimmt das Verhalten des Systems bei der Zugriffsgewährung.

Wenn ACL Strict Mode deaktiviert ist, hat man automatisch Zugriff auf alle Entitäten, auch auf solche, die eigentlich nicht für den Nutzer konfiguriert sind. Wenn ACL Strict Mode aktiviert ist, hat man automatisch Zugriff nur auf die freigegebene Entitäten.

Wir empfehlen, den ACL Strict Mode von Anfang an zu aktivieren.



## Was ist bedingte Feldlogik?

Es ist möglich, die Bedingungen einzustellen, ob ein Feld sichtbar, read-only oder ein Pflichtfeld sein sollte. Als Bedingung kann z.B. der Wert eines anderen Feldes herangezogen werden, z.B. wenn der Status eines Eintrages “Approved” ist, kann das Feld “Name” nicht mehr editierbar sein.

  

## Kann man TreoPIM aus dem Adminbereich aktualisieren?

Es ist möglich, das TreoPIM direkt aus dem Adminbereich zu aktualisieren. Wir empfehlen aber, dies nur von Ihren TreoPIM-Entwicklern machen zu lassen. Der Admin erhält eine Benachrichtigung, wenn ein Update verfügbar ist.



## Kann man TreoPIM-Module aus dem Adminbereich aktualisieren?

Ja, dank dem Modul Manager ist es möglich, sowohl die individuellen als auch die offiziellen Module von TreoPIM direkt aus dem Adminbereich zu installieren, zu aktualisieren, zu aktivieren oder zu deaktivieren sowie zu deinstallieren.

Wir empfehlen aber, Aktualisierungen nur von Ihren TreoPIM-Entwicklern vornehmen zu lassen.

  

## Kann man Aufgaben und Tasks nach Zeitplan im Hintergrund ausführen lassen?

Ja, in TreoPIM gibt es “Scheduled Jobs” dafür. Es is möglich zu konfigurieren, welche Skripte nach welchem Zeitplan auszuführen sind. Es können sowohl die System-Skripte als auch die individuellen Skripte als “Scheduled Jobs” ausgeführt werden.

  

## Kann man das Theme ändern?

In TreoPIM gibt es ein vordefiniertes Theme - Treo Dark Theme. Man kann auch eigene Themes erstellen lassen, um z.B. ein Farbschema an die Firmenfarben anzupassen.

  

## Ist die Navigationsleiste flexibel platzierbar?

Ja, die Navigationsleiste kann links, oben oder rechts platziert werden. Der Platz wird im Theme festgelegt. In der Standardansicht von TreoPIM wird die Navigationsleiste links platziert.

  

## Kann man die Navigationsleiste konfigurieren?

Ja, Sie können die Reihenfolge der Elemente sowie die Icons für Elemente konfigurieren. Wenn Sie unser 2-Level-Navigation-Modul nutzen, können Sie auch Gruppen von Elementen definieren sowie diese Gruppen und die Elemente innerhalb einer Gruppe anordnen.

  

## Kann man die Anzahl der Einträge auf Listenansichten ändern?

Ja, man kann einstellen, wie viele Einträge auf allen Listenansichten anzuzeigen sind.

  

## Kann das Dashboard voreingestellt werden?

Ja, der Administrator kann die Standard-Dashboards über Drag-and-Drop konfigurieren, es kann mehr als ein Dashboard konfiguriert werden.

  

## Gibt es einen Aktionslog?

Ja, wenn eingestellt, kann der Administrator alle Handlungen von allen Nutzern im System sehr genau nachvollziehen. Es wird dokumentiert, wer, an welcher Entität, welche Werte (Felder) und welche Einträge geändert hat. Die Lesezugriffe werden ebenfalls dokumentiert. Die Einträge im Aktionslog können durchgesucht werden.

  

## Werden die Einträge wirklich gelöscht? Kann man gelöschte Daten wiederherstellen?

Um die Konsistenz des Systems nicht zu gefährden, werden in TreoPIM keine Einträge gelöscht. Diese erhalten nur eine Eigenschaft “isDeleted”, damit das System weiß, dass dieser Eintrag den Nutzern nicht mehr anzuzeigen ist.

Somit ist es möglich, die “gelöschten” Daten wiederherzustellen. Das sollte am besten von TreoLabs GmbH oder von Ihren TreoPIM-Entwicklern erfolgen.
