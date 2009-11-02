<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');

	class contentExtensionBulkImporterImport extends AdministrationPage {
		protected $_driver;

		public function __viewIndex() {
			$this->_driver = $this->_Parent->ExtensionManager->create('bulkimporter');

			$this->setPageType('form');
			$this->Form->setAttribute('enctype', 'multipart/form-data');
			$this->setTitle('Symphony &ndash; Bulk Importer');

			$this->appendSubheading('Import');

		// Settings --------------------------------------------------------

			$container = new XMLElement('fieldset');
			$container->setAttribute('class', 'settings');
			$container->appendChild(
				new XMLElement('legend', 'Select <code>.zip</code> to import')
			);

			$group = new XMLElement('div');
			$group->setAttribute('class', 'group');

			$this->__viewIndexFileInterface($group);
			$this->__viewIndexSectionName($group);

			$this->__viewIndexSectionLinks($group);
			$this->__viewIndexLinkedEntries($group);

			$container->appendChild($group);
			$this->Form->appendChild($container);

		//---------------------------------------------------------------------

			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');

			$attr = array('accesskey' => 's');
			$div->appendChild(Widget::Input('action[save]', 'Import', 'submit', $attr));

			$this->Form->appendChild($div);
		}

	/*-------------------------------------------------------------------------
		Sections:
	-------------------------------------------------------------------------*/
		public function __viewIndexSectionName($context) {
			$sectionManager = new SectionManager($this->_Parent);

			/*	Label	*/
			$label = Widget::Label(__('Target Section'));

			/*	Fetch sections & populate a dropdown	*/
			$sections = $sectionManager->fetch(NULL, 'ASC', 'name');
			$options = array();

			if(is_array($sections) && !empty($sections)){
				foreach($sections as $s) {
					$options[] = array(
						$s->get('id'),
						($fields['target'] == $s->get('id')),
						$s->get('name')
					);
				}
			}

			$label->appendChild(Widget::Select('fields[target]', $options, array('id' => 'context')));

			$context->appendChild($label);

		}

		public function __viewIndexSectionLinks($context) {
			$sectionManager = new SectionManager($this->_Parent);

			/*	Label	*/
			$label = Widget::Label(__('Available Section Links'));
			$label->appendChild(new XMLElement("span",__('Ignore if you do not wish to link entries to another section')));
			$label->appendChild(Widget::Select('fields[linked-section]', null, array('id' => 'linked-section')));

			$context->appendChild($label);
		}

		public function __viewIndexLinkedEntries($context) {
			$sectionManager = new SectionManager($this->_Parent);

			/*	Label	*/
			$label = Widget::Label(__('Section Link Entries'));
			$label->appendChild(Widget::Select('fields[linked-entry]', null, array('id' => 'linked-entry')));

			$context->appendChild($label);
		}

	/*-------------------------------------------------------------------------
		File Interface:
	-------------------------------------------------------------------------*/
		public function __viewIndexFileInterface($context) {
			$label = Widget::Label(__('File'));
			$label->appendChild(Widget::Input('fields[file]', NULL, 'file'));

			$context->appendChild($label);
		}



		public function __actionIndex() {
			if (empty($this->_driver)) {
				$this->_driver = $this->_Parent->ExtensionManager->create('bulkimporter');
			}

			if (@isset($_POST['action']['save'])) {
				$this->prepareUpload($_POST['fields']);
			}
		}

		public function prepareUpload($post) {
			$sectionManager = new SectionManager($this->_Parent);
			$section = $sectionManager->fetch($post['target']);
			$this->_driver->target_section = $section;
			$this->_driver->linked_entry = array(
										"linked-section" => $post['linked-section'],
										"linked-entry" => $post['linked-entry']);
			$field = null;

			foreach($this->_driver->getSupportedFields() as $f) {
				$field = $section->fetchFields($f);
			}

			/* Just check that the section has a valid field */
			if(!is_null($field)) {

				if($this->_driver->beginProcess()) {
					$this->_driver->commitFiles($this->_Parent);

					/*	Status Message */
					$uploaded = $failed = 0;

					foreach($this->_driver->files as $file) {
						($file->get('uploaded')) ? $uploaded++ : $failed++;
					}

					if($uploaded == 0) {
						$this->pageAlert(
							__("You didn't upload any files, %d failed", array($failed)),
							Alert::ERROR
						);
					} else {
						$result = 'Bulk import complete to <code>%1$s</code>, %2$d were uploaded, %3$d failed.';
						$this->pageAlert(
							__($result,
								array(
									$this->_driver->target_section->get('handle'),
									$uploaded,
									$failed)
							),
							Alert::SUCCESS
						);
					}

				} else {
					$this->pageAlert(
						__("You didn't upload any files..", NULL),
						Alert::ERROR
					);
				}

				$this->_driver->cleanUp(array($uploaded,$failed));
			} else {
				$error = 'An error occured, are you sure <code>%1$s</code> has a valid upload field? Available: <code>%2$s</code>';

				$this->pageAlert(
					__($error,
						array(
							$this->_driver->target_section->get('handle'),
							implode(", ",$this->_driver->getSupportedFields())
						)
					),
					Alert::ERROR
				);
			}
		}
	}
?>